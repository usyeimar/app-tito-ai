import logging
import sys
import warnings
from contextlib import asynccontextmanager
from dotenv import load_dotenv
from fastapi import FastAPI
from app.services.task_manager import task_manager

# Suppress deprecation warnings from external libraries
warnings.filterwarnings("ignore", category=DeprecationWarning)
warnings.filterwarnings("ignore", category=FutureWarning, module="websockets")
warnings.filterwarnings("ignore", category=DeprecationWarning, module="pipecat")

load_dotenv()

# Configure logging to output to stdout with appropriate format
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s | %(levelname)-8s | %(name)s:%(lineno)d - %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
    handlers=[logging.StreamHandler(sys.stdout)],
)

logger = logging.getLogger(__name__)


@asynccontextmanager
async def lifespan(app: FastAPI):
    # Startup
    logger.info("Application starting up...")

    # ── SIP Bridge (optional) ────────────────────────────────────────────────
    from app.core.config import settings as _settings

    sip_handler = None
    audiosocket_server = None
    ami_controller = None

    if _settings.SIP_ENABLED:
        logger.info(
            "SIP Bridge enabled — starting AudioSocket server and AMI controller"
        )
        from app.services.sip.audiosocket_server import AudioSocketServer
        from app.services.sip.ami_controller import AMIController
        from app.services.sip.call_handler import SIPCallHandler

        # AudioSocket TCP server
        audiosocket_server = AudioSocketServer(
            host=_settings.SIP_AUDIOSOCKET_HOST,
            port=_settings.SIP_AUDIOSOCKET_PORT,
        )

        # AMI controller (optional — can work without it)
        ami_controller = None
        if _settings.ASTERISK_AMI_SECRET:
            ami_controller = AMIController(
                host=_settings.ASTERISK_AMI_HOST,
                port=_settings.ASTERISK_AMI_PORT,
                username=_settings.ASTERISK_AMI_USER,
                secret=_settings.ASTERISK_AMI_SECRET,
            )
            try:
                await ami_controller.connect()
                logger.info("AMI controller connected")
            except Exception as e:
                logger.warning(
                    f"AMI connection failed (calls will still work via AudioSocket): {e}"
                )
                ami_controller = None

        # Inject AMI into trunk_service for outbound origination
        if ami_controller:
            from app.services.trunk_service import trunk_service as _trunk_service

            _trunk_service.set_ami_controller(ami_controller)

        # Wire up the call handler
        sip_handler = SIPCallHandler(audiosocket_server, ami=ami_controller)
        await audiosocket_server.start()
        logger.info(
            f"SIP Bridge ready — AudioSocket on {_settings.SIP_AUDIOSOCKET_HOST}:{_settings.SIP_AUDIOSOCKET_PORT}"
        )

    # Expose SIP state for health check
    app.state.sip_enabled = _settings.SIP_ENABLED
    app.state.audiosocket_server = audiosocket_server
    app.state.ami_controller = ami_controller

    yield

    # Shutdown — uvicorn triggers this on SIGINT/SIGTERM
    logger.info("Application shutting down gracefully...")

    # 0.3 Graceful Shutdown: notify and stop all sessions
    from app.services.session_manager import session_manager

    logger.info(f"shutdown_started | active_sessions: {task_manager.count()}")

    # Notify all connected WebSocket clients before closing
    await session_manager.broadcast(
        {"event": "server.shutdown", "message": "Server is shutting down"}
    )

    # Stop all asyncio Tasks
    await task_manager.stop_all()

    # Stop SIP Bridge
    if audiosocket_server:
        await audiosocket_server.stop()
        logger.info("AudioSocket server stopped")
    if ami_controller:
        await ami_controller.disconnect()
        logger.info("AMI controller disconnected")

    logger.info("Application shutdown complete.")


from app.core.config import settings
from app.core.errors import setup_exception_handlers
from app.api.v1.api import api_router
from app.schemas.errors import APIErrorResponse


def setup_websocket_docs(app: FastAPI):
    """Add WebSocket endpoints to OpenAPI spec."""
    return None  # Disable for now - causes issues


app = FastAPI(
    title=settings.PROJECT_NAME,
    openapi_url="/api/v1/openapi.json",
    docs_url="/docs",
    redoc_url="/redoc",
    version="1.0.0",
    lifespan=lifespan,
    description="""
## Tito AI Runners API

Plataforma de orquestación de **agentes conversacionales de voz en tiempo real**.

### Endpoints Principales

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/api/v1/sessions/` | Crear nueva sesión de agente de voz |
| GET | `/api/v1/sessions/` | Listar sesiones activas |
| GET | `/api/v1/sessions/{id}` | Obtener estado de sesión |
| DELETE | `/api/v1/sessions/{id}` | Terminar sesión |
| GET | `/api/v1/sessions/{id}/transcript` | WebSocket para transcripciones |
| GET | `/api/v1/sessions/{id}/chat` | WebSocket para chat de texto |
| GET | `/api/v1/sessions/{id}/audio` | WebSocket para audio PCM directo |
| GET | `/api/v1/metrics/` | Métricas Prometheus |
| GET | `/api/v1/sip/` | Endpoints SIP |
| GET | `/health` | Health check |

### Flujo Principal

```
Cliente → POST /api/v1/sessions/ → Runner crea sala + pipeline → Cliente se une con token
```

### Proveedores Soportados

| Componente | Proveedores |
|------------|-------------|
| **LLM** | OpenAI, Anthropic, Google, Groq, Together, Mistral |
| **STT** | Deepgram, Google, Gladia, AssemblyAI, AWS |
| **TTS** | Cartesia, ElevenLabs, Deepgram, PlayHT, Azure |
| **WebRTC** | Daily.co, LiveKit, WebSocket Directo |

### WebSockets en Tiempo Real

| Endpoint | Propósito | Formato |
|----------|-----------|--------|
| `/sessions/{id}/transcript` | Transcripciones | TEXT/JSON |
| `/sessions/{id}/chat` | Chat bidireccional | TEXT/JSON |
| `/sessions/{id}/audio` | Audio PCM | BINARY |

### SIP (Asterisk)

| Endpoint | Descripción |
|----------|-------------|
| `/sip/media/{connection_id}` | WebSocket para chan_websocket |
| `/sip/calls/{call_id}` | Estado de llamada |
| `/sip/dialplan/{workspace}` | Reglas de dialplan |
| `/sip/health` | Health check SIP |

### Eventos (Webhooks)

- `session.started` - Sesión iniciada
- `session.ended` - Sesión terminada  
- `session.error` - Error en sesión
- `transcript.final` - Transcripción final
    """,
    contact={
        "name": "Tito AI Team",
        "url": "https://tito.ai",
    },
    license_info={
        "name": "Proprietary",
    },
    openapi_tags=[
        {
            "name": "Sessions",
            "description": "Gestión del ciclo de vida de sesiones de agentes de voz. Crear, listar, terminar y WebSockets.",
        },
        {
            "name": "SIP Trunks",
            "description": "Gestión de SIP Trunks para conectar PBX externas a agentes de voz. Modos: inbound, register, outbound.",
        },
        {
            "name": "SIP",
            "description": "Integración con Asterisk via AudioSocket/WebSocket. Gestión de llamadas, dialplan y transcoding.",
        },
        {
            "name": "Metrics",
            "description": "Métricas Prometheus para monitoreo y alertas.",
        },
        {
            "name": "Health",
            "description": "Health checks para Kubernetes y load balancers.",
        },
        {
            "name": "Deployments",
            "description": "Gestión de despliegues de agentes.",
        },
    ],
    responses={
        400: {
            "model": APIErrorResponse,
            "description": "Bad Request - Request malformado",
        },
        401: {
            "model": APIErrorResponse,
            "description": "Unauthorized - Sin autenticacion",
        },
        403: {"model": APIErrorResponse, "description": "Forbidden - Sin permisos"},
        404: {
            "model": APIErrorResponse,
            "description": "Not Found - Recurso no encontrado",
        },
        422: {
            "model": APIErrorResponse,
            "description": "Validation Error - Campos requeridos faltantes o tipos invalidos",
        },
        500: {
            "model": APIErrorResponse,
            "description": "Internal Server Error - Error inesperado del servidor",
        },
    },
)

# Setup global exception handlers
setup_exception_handlers(app)
app.include_router(api_router, prefix="/api/v1")

# Add WebSocket documentation to OpenAPI
setup_websocket_docs(app)


@app.get(
    "/health",
    tags=["Health"],
    summary="Health Check",
    response_description="Estado actual del runner",
)
async def health():
    """
    Health check para **Kubernetes** y **Load Balancers**.

    Devuelve el estado operativo del runner, incluyendo:
    - Numero de sesiones activas
    - Capacidad maxima configurada
    - Si el runner esta al limite de capacidad
    - Identificador unico del pod/host

    **Uso tipico**: Configurar como `livenessProbe` y `readinessProbe` en K8s.
    Cuando `at_capacity` es `true`, el load balancer deberia redirigir a otro runner.
    """
    active = task_manager.count()
    result = {
        "status": "OK",
        "active_sessions": active,
        "max_sessions": settings.MAX_CONCURRENT_SESSIONS,
        "at_capacity": active >= settings.MAX_CONCURRENT_SESSIONS,
        "host_id": settings.HOST_ID,
    }

    # SIP Bridge status
    sip_enabled = getattr(app.state, "sip_enabled", False)
    if sip_enabled:
        audiosocket = getattr(app.state, "audiosocket_server", None)
        ami = getattr(app.state, "ami_controller", None)
        result["sip"] = {
            "enabled": True,
            "audiosocket": {
                "listening": audiosocket is not None
                and audiosocket._server is not None,
                "active_connections": len(audiosocket.connections)
                if audiosocket
                else 0,
            },
            "ami": {
                "connected": ami.connected if ami else False,
            },
        }

    return result
