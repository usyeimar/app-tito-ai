# Plan: API de SIP Trunks (Customer-Owned Trunks)

## Contexto

Actualmente el modelo SIP en `deployment_service.py` provisiona **1 SIP URI por agente** (`sip:agent-id@workspace.sip.tito.ai`). Esto obliga al cliente a crear un trunk separado en su PBX por cada agente.

El nuevo modelo introduce **Customer Trunks** con tres modos de conexión:

- **`inbound`**: El cliente configura un trunk en su PBX apuntando a tu Asterisk. Tu sistema recibe las llamadas.
- **`register`**: Tu Asterisk se registra como una extensión en la PBX del cliente. El cliente solo asigna un número de extensión.
- **`outbound`**: Tu sistema inicia llamadas salientes a números telefónicos. El agente IA llama al usuario (campañas, cobranzas, recordatorios, etc.).

## Modelo Conceptual

### Modo Inbound (trunk)

```
Cliente configura en su PBX:
  Trunk: alloy-finance.sip.tito.ai (1 trunk, N extensiones)
  Ext 100 → agente "luna-soporte"
  Ext 200 → agente "ventas-bot"
  Ext 300 → agente "cobranzas-ai"

Flujo:
  PBX Cliente ──SIP INVITE (ext 100)──► Tu Asterisk → Pipeline "luna-soporte"
```

- **Quién configura**: Admin de la PBX del cliente (trunk + rutas salientes)
- **Caso de uso**: Call centers, PBX enterprise, integraciones avanzadas
- **Ventaja**: 1 trunk → N agentes por extensión

### Modo Register

```
Tu Asterisk se registra en la PBX del cliente:
  Register → pbx.alloy.com:5060 como extensión 100

Flujo:
  Tu Asterisk ──SIP REGISTER──► PBX Cliente (ext 100 = tu agente)
  Alguien marca ext 100 → PBX Cliente ──INVITE──► Tu Asterisk → Pipeline
```

- **Quién configura**: Solo tú (el cliente da credenciales de extensión)
- **Caso de uso**: PyMEs, PBX cloud (3CX, Grandstream, FreePBX), setup rápido
- **Ventaja**: El cliente no necesita saber configurar trunks SIP
- **Nota**: 1 registro = 1 agente (para N agentes, N registros)

### Modo Outbound (llamadas salientes)

```
Tu sistema inicia la llamada al usuario:
  Asterisk ──SIP INVITE──► Proveedor SIP (Twilio, VoIP trunk) ──► Red telefónica ──► Teléfono usuario

Flujo:
  POST /api/v1/trunks/{trunk_id}/call
  → Tu Asterisk origina llamada via trunk outbound
  → Usuario contesta
  → AudioSocket → Pipeline agente IA habla con el usuario
```

- **Quién inicia**: Tu sistema (vía API)
- **Caso de uso**: Campañas de cobranza, recordatorios de citas, encuestas, seguimiento post-venta, verificación de identidad
- **Requisito**: Un proveedor SIP con capacidad de originar llamadas (Twilio SIP Trunk, VoIP.ms, un carrier SIP, o la propia PBX del cliente vía trunk inbound/register)
- **Nota**: El trunk outbound define las credenciales del carrier/proveedor por donde salen las llamadas. El `caller_id` (número que ve el usuario) depende del proveedor.

### Tabla comparativa

| Aspecto | `inbound` | `register` | `outbound` |
|---------|-----------|------------|------------|
| Dirección SIP | Cliente → Tu Asterisk | Tu Asterisk → PBX Cliente (registro) | Tu Asterisk → Carrier/PBX → Teléfono |
| Quién inicia la llamada | Usuario externo | Usuario externo | **Tu sistema** (vía API) |
| Config del cliente | Crear trunk + rutas | Solo dar credenciales de ext | Proveer credenciales del carrier SIP |
| Complejidad cliente | Media-Alta | **Baja** | Baja (solo da datos del carrier) |
| N agentes | 1 trunk → N extensiones | 1 registro = 1 agente | Se especifica por llamada |
| NAT/Firewall | Cliente debe alcanzar tu IP | Tú inicias conexión | Tú inicias conexión |
| Monitoreo extra | No | `registration_status` | `call_status` (ringing/answered/completed/failed) |
| Caller ID | N/A (lo define el que llama) | N/A | Configurable en el trunk |

## Entidades

- **Trunk**: Conexión SIP con credenciales, modo, límites, estado.
- **TrunkRoute**: Mapeo extensión/DID → agent_id (solo para modo `inbound`).
- **TrunkRegisterConfig**: Datos de la PBX remota (solo para modo `register`).
- **TrunkOutboundConfig**: Datos del carrier/proveedor SIP para originar llamadas (solo para modo `outbound`).

## Cambios por Archivo

### 1. Nuevo: `app/schemas/trunks.py`

```python
from typing import Optional, Literal, Dict, Any, List
from pydantic import BaseModel, Field, ConfigDict
import time


# ── Enums y configs compartidas ──

class TrunkRouteConfig(BaseModel):
    """Mapeo de extensión/DID a un agente (solo para mode=inbound)."""
    extension: str = Field(..., description="Extensión o DID que se marcó.", examples=["100", "+573001234567"])
    agent_id: str = Field(..., description="ID del agente que atenderá.", examples=["luna-soporte"])
    priority: int = Field(0, description="Prioridad de routing (menor = más prioritario).")
    enabled: bool = Field(True, description="Si esta ruta está activa.")


class TrunkInboundAuthConfig(BaseModel):
    """Autenticación para trunks inbound (el cliente se conecta a ti)."""
    auth_type: Literal["digest", "ip"] = Field(..., description="Tipo de autenticación SIP.")
    username: Optional[str] = Field(None, description="Usuario para Digest Auth (auto-generado si no se provee).")
    password: Optional[str] = Field(None, description="Password para Digest Auth (auto-generado si no se provee).")
    allowed_ips: List[str] = Field(default_factory=list, description="IPs permitidas (solo para auth_type=ip).", examples=[["203.0.113.10"]])


class TrunkRegisterConfig(BaseModel):
    """Configuración para registrarse en una PBX remota (mode=register)."""
    remote_host: str = Field(..., description="Host/IP de la PBX del cliente.", examples=["pbx.alloy.com"])
    remote_port: int = Field(5060, description="Puerto SIP de la PBX remota.")
    username: str = Field(..., description="Usuario/extensión en la PBX remota.", examples=["100"])
    password: str = Field(..., description="Password de la extensión remota.")
    domain: Optional[str] = Field(None, description="Dominio SIP si difiere del host.")
    transport: Literal["udp", "tcp", "tls"] = Field("udp", description="Protocolo de transporte SIP.")
    register_interval: int = Field(120, description="Segundos entre re-registros (keepalive).")


class TrunkOutboundConfig(BaseModel):
    """Configuración del carrier/proveedor SIP para llamadas salientes (mode=outbound)."""
    carrier_host: str = Field(..., description="Host del carrier SIP.", examples=["sip.twilio.com", "trunk.voipms.com"])
    carrier_port: int = Field(5060, description="Puerto SIP del carrier.")
    username: str = Field(..., description="Usuario/cuenta en el carrier.", examples=["ACxxxxx"])
    password: str = Field(..., description="Password/auth token del carrier.")
    domain: Optional[str] = Field(None, description="Dominio SIP del carrier (si difiere del host).")
    transport: Literal["udp", "tcp", "tls"] = Field("udp", description="Protocolo de transporte SIP.")
    caller_id: Optional[str] = Field(None, description="Número que verá el destinatario (caller ID).", examples=["+573001234567"])
    prefix: Optional[str] = Field(None, description="Prefijo de marcación del carrier (ej: '9' para línea externa).")
    headers: Dict[str, str] = Field(default_factory=dict, description="Headers SIP custom para el carrier.")


class OutboundCallRequest(BaseModel):
    """Request para iniciar una llamada saliente."""
    to: str = Field(..., description="Número destino en formato E.164.", examples=["+573001234567"])
    agent_id: str = Field(..., description="ID del agente que manejará la llamada.", examples=["luna-cobranzas"])
    caller_id: Optional[str] = Field(None, description="Override del caller ID (si no se usa, se toma del trunk).")
    timeout_seconds: int = Field(30, description="Segundos esperando que contesten antes de desistir.")
    callback_url: Optional[str] = Field(None, description="URL para webhooks de esta llamada específica.")
    metadata: Dict[str, Any] = Field(default_factory=dict, description="Datos extra inyectados al contexto del agente (nombre del cliente, deuda, etc.).")


class OutboundCallResponse(BaseModel):
    """Respuesta al iniciar una llamada saliente."""
    call_id: str = Field(..., description="ID único de la llamada.", examples=["call_a1b2c3d4e5f6"])
    trunk_id: str
    agent_id: str
    to: str
    caller_id: Optional[str]
    call_status: Literal["queued", "ringing", "answered", "completed", "failed", "no_answer", "busy"] = "queued"
    session_id: Optional[str] = Field(None, description="ID de sesión Pipecat (disponible cuando call_status=answered).")
    created_at: float = Field(default_factory=time.time)
    links: Dict[str, Any] = Field(default_factory=dict, alias="_links")

    model_config = ConfigDict(populate_by_name=True)


# ── Requests ──

class CreateTrunkRequest(BaseModel):
    """Request para crear un trunk SIP."""
    name: str = Field(..., description="Nombre descriptivo del trunk.", examples=["Trunk Principal Alloy"])
    tenant_id: str = Field(..., description="ID del tenant/organización.", examples=["tenant-abc"])
    workspace_slug: str = Field(..., description="Slug del workspace.", examples=["alloy-finance"])
    mode: Literal["inbound", "register", "outbound"] = Field(..., description="Modo de conexión SIP.")

    # Para mode=inbound
    inbound_auth: Optional[TrunkInboundAuthConfig] = Field(None, description="Autenticación (requerido si mode=inbound).")
    routes: List[TrunkRouteConfig] = Field(default_factory=list, description="Rutas iniciales ext→agente (solo mode=inbound).")

    # Para mode=register
    register: Optional[TrunkRegisterConfig] = Field(None, description="Config de registro remoto (requerido si mode=register).")
    agent_id: Optional[str] = Field(None, description="Agente asociado (requerido si mode=register, ya que 1 registro = 1 agente).")

    # Para mode=outbound
    outbound: Optional[TrunkOutboundConfig] = Field(None, description="Config del carrier SIP (requerido si mode=outbound).")

    # Compartidos
    max_concurrent_calls: int = Field(5, description="Máximo de llamadas simultáneas en este trunk.")
    codecs: List[str] = Field(default_factory=lambda: ["ulaw", "alaw", "opus"], description="Codecs SIP permitidos.")


class UpdateTrunkRequest(BaseModel):
    """Request para actualizar un trunk (campos parciales)."""
    name: Optional[str] = None
    inbound_auth: Optional[TrunkInboundAuthConfig] = None
    register: Optional[TrunkRegisterConfig] = None
    outbound: Optional[TrunkOutboundConfig] = None
    agent_id: Optional[str] = None
    max_concurrent_calls: Optional[int] = None
    codecs: Optional[List[str]] = None
    enabled: Optional[bool] = None


# ── Responses ──

class TrunkLink(BaseModel):
    """Enlace HATEOAS."""
    href: str = Field(..., description="URL del recurso.")
    method: str = Field(..., description="Método HTTP.")


class TrunkResponse(BaseModel):
    """Respuesta completa de un trunk."""
    trunk_id: str = Field(..., description="ID único del trunk.", examples=["trk_a1b2c3d4e5f6"])
    name: str
    tenant_id: str
    workspace_slug: str
    mode: Literal["inbound", "register", "outbound"]

    # Inbound
    sip_host: Optional[str] = Field(None, description="Host SIP para mode=inbound.", examples=["alloy-finance.sip.tito.ai"])
    sip_port: int = Field(5060)
    inbound_auth: Optional[TrunkInboundAuthConfig] = Field(None, description="Auth config (password enmascarado en GET).")
    routes: List[TrunkRouteConfig] = Field(default_factory=list)

    # Register
    register: Optional[TrunkRegisterConfig] = Field(None, description="Config de registro (password enmascarado en GET).")
    registration_status: Optional[Literal["registered", "unregistered", "rejected", "retrying"]] = Field(
        None, description="Estado actual del registro SIP (solo mode=register)."
    )
    agent_id: Optional[str] = Field(None, description="Agente asociado (solo mode=register).")

    # Outbound
    outbound: Optional[TrunkOutboundConfig] = Field(None, description="Config del carrier (password enmascarado en GET).")
    total_calls_made: int = Field(0, description="Total de llamadas originadas por este trunk.")

    # Compartidos
    max_concurrent_calls: int = 5
    active_calls: int = Field(0, description="Llamadas activas en este momento.")
    codecs: List[str] = Field(default_factory=list)
    status: Literal["active", "inactive", "suspended"] = "active"
    created_at: float = Field(default_factory=time.time)
    updated_at: float = Field(default_factory=time.time)
    links: Dict[str, TrunkLink] = Field(default_factory=dict, alias="_links")

    model_config = ConfigDict(populate_by_name=True)


class TrunkListResponse(BaseModel):
    """Lista de trunks."""
    trunks: List[TrunkResponse] = Field(default_factory=list)
    count: int = Field(..., description="Total de trunks.")
    links: Dict[str, TrunkLink] = Field(default_factory=dict, alias="_links")

    model_config = ConfigDict(populate_by_name=True)


class TrunkRouteResponse(BaseModel):
    """Respuesta al agregar una ruta."""
    trunk_id: str
    route: TrunkRouteConfig
    total_routes: int
    links: Dict[str, TrunkLink] = Field(default_factory=dict, alias="_links")

    model_config = ConfigDict(populate_by_name=True)


class TrunkCredentialsResponse(BaseModel):
    """Respuesta con credenciales regeneradas (password en claro solo aquí)."""
    trunk_id: str
    mode: Literal["inbound", "register", "outbound"]
    inbound_auth: Optional[TrunkInboundAuthConfig] = None
    register: Optional[TrunkRegisterConfig] = None
    outbound: Optional[TrunkOutboundConfig] = None
    status: str = "CREDENTIALS_ROTATED"
    links: Dict[str, TrunkLink] = Field(default_factory=dict, alias="_links")

    model_config = ConfigDict(populate_by_name=True)
```

### 2. Nuevo: `app/services/trunk_service.py`

Servicio Redis-backed. Sigue el patrón de `deployment_service.py` (singleton, usa `session_manager._redis`):

```python
class TrunkService:
    """Gestión de SIP Trunks en Redis."""
    DOMAIN_SUFFIX = "sip.tito.ai"

    def __init__(self):
        self._redis = session_manager._redis

    # ── CRUD Trunk ──

    async def create_trunk(self, request: CreateTrunkRequest) -> dict:
        # 1. Validar según mode:
        #    - inbound: requiere inbound_auth
        #    - register: requiere register + agent_id
        #    - outbound: requiere outbound
        # 2. Generar trunk_id: f"trk_{uuid.uuid4().hex[:12]}"
        # 3. Para inbound: auto-generar username/password si no se proveen
        # 4. Construir dict con todos los campos + timestamps
        # 5. Para inbound: sip_host = f"{workspace_slug}.{DOMAIN_SUFFIX}"
        # 6. Para register: registration_status = "unregistered" (se actualizará por AMI)
        # 7. Para outbound: total_calls_made = 0
        # 8. Persistir: Redis SET "trunk:{trunk_id}" → JSON
        # 9. Índice: Redis SADD "trunk:index:{workspace_slug}" → trunk_id
        # 10. Retornar dict

    async def get_trunk(self, trunk_id: str) -> Optional[dict]:
        # Leer de Redis, enmascarar passwords (reemplazar con "********")

    async def list_trunks(self, workspace_slug: str) -> list[dict]:
        # SMEMBERS "trunk:index:{workspace_slug}" → lista de trunk_ids
        # Para cada uno: get_trunk()

    async def update_trunk(self, trunk_id: str, request: UpdateTrunkRequest) -> dict:
        # Leer trunk actual (sin enmascarar)
        # Merge solo campos no-None del request
        # Persistir de vuelta

    async def delete_trunk(self, trunk_id: str) -> bool:
        # Leer trunk para obtener workspace_slug
        # SREM "trunk:index:{workspace_slug}" trunk_id
        # DEL "trunk:{trunk_id}"
        # DEL "trunk:calls:{trunk_id}"

    # ── Rutas (solo para mode=inbound) ──

    async def add_route(self, trunk_id: str, route: TrunkRouteConfig) -> dict:
        # Leer trunk (sin enmascarar)
        # Validar que mode=inbound
        # Validar que extension no esté duplicada en routes[]
        # Append route, persistir

    async def remove_route(self, trunk_id: str, extension: str) -> bool:
        # Leer trunk, filtrar routes[] sin la extensión, persistir

    # ── Credenciales ──

    async def rotate_credentials(self, trunk_id: str) -> dict:
        # Leer trunk (sin enmascarar)
        # Si inbound: nuevo password para inbound_auth
        # Si register: nuevo password para register config
        # Si outbound: nuevo password para outbound config
        # Persistir, retornar con password en claro

    # ── Resolución de llamadas (usado por SIP Bridge) ──

    async def resolve_inbound_call(self, workspace_slug: str, extension: str) -> Optional[dict]:
        # Para mode=inbound: busca en trunks del workspace la ruta que matchea extension
        # Retorna {"trunk_id": ..., "agent_id": ..., "trunk_data": ...}

    async def resolve_register_call(self, trunk_id: str) -> Optional[dict]:
        # Para mode=register: el trunk_id se conoce por la registración
        # Retorna {"trunk_id": ..., "agent_id": ..., "trunk_data": ...}

    # ── Llamadas salientes (mode=outbound) ──

    async def originate_call(self, trunk_id: str, request: OutboundCallRequest) -> dict:
        # 1. Leer trunk (sin enmascarar), validar mode=outbound y status=active
        # 2. Validar max_concurrent_calls (INCR trunk:calls:{trunk_id}, si > max → DECR y rechazar 429)
        # 3. Generar call_id: f"call_{uuid.uuid4().hex[:12]}"
        # 4. Determinar caller_id: request.caller_id → trunk.outbound.caller_id → None
        # 5. Persistir call state en Redis: "call:{call_id}" → JSON con TTL 1h
        #    {call_id, trunk_id, agent_id, to, caller_id, call_status: "queued", created_at, metadata}
        # 6. En fase SIP Bridge (futuro): enviar comando a Asterisk vía AMI para originar
        #    Action: Originate
        #    Channel: PJSIP/{to}@{trunk_endpoint}
        #    Context: tito-outbound
        #    Exten: s
        #    CallerID: {caller_id}
        #    Variable: CALL_ID={call_id},AGENT_ID={agent_id}
        # 7. Retornar {call_id, trunk_id, agent_id, to, call_status: "queued"}

    async def get_call(self, call_id: str) -> Optional[dict]:
        # Leer "call:{call_id}" de Redis

    async def list_calls(self, trunk_id: str) -> list[dict]:
        # SMEMBERS "call:index:{trunk_id}" → lista de call_ids activos
        # Para cada uno: get_call()

    async def cancel_call(self, call_id: str) -> bool:
        # Si call_status in (queued, ringing): cancelar
        # En fase SIP Bridge: AMI Action Hangup
        # Actualizar call_status = "cancelled"
        # DECR trunk:calls:{trunk_id}

    async def update_call_status(self, call_id: str, new_status: str, session_id: str = None) -> dict:
        # Llamado por el SIP Bridge cuando cambia el estado de la llamada
        # Actualiza call_status + session_id (si answered)
        # Si terminal (completed/failed/no_answer/busy): DECR trunk:calls, SREM call:index
        # Emite webhook si callback_url está configurado

    # ── Helpers ──

    async def _get_trunk_raw(self, trunk_id: str) -> Optional[dict]:
        # Lee sin enmascarar (para uso interno)

    def _mask_password(self, data: dict) -> dict:
        # Reemplaza passwords con "********" para responses GET

    async def increment_active_calls(self, trunk_id: str) -> int:
        # INCR "trunk:calls:{trunk_id}", retorna nuevo valor
        # Usado por SIP Bridge al iniciar llamada (inbound/register) o originate_call (outbound)

    async def decrement_active_calls(self, trunk_id: str) -> int:
        # DECR "trunk:calls:{trunk_id}", mínimo 0
        # Usado por SIP Bridge al terminar llamada

trunk_service = TrunkService()
```

**Redis Keys:**
- `trunk:{trunk_id}` → JSON completo del trunk (con passwords en claro)
- `trunk:index:{workspace_slug}` → SET de trunk_ids del workspace
- `trunk:calls:{trunk_id}` → INT counter de llamadas activas
- `call:{call_id}` → JSON del estado de llamada outbound (TTL 1h)
- `call:index:{trunk_id}` → SET de call_ids activos del trunk

### 3. Nuevo: `app/api/v1/trunks.py`

Endpoints REST con HATEOAS. Sigue patrón de `sessions.py`:

```
# ── CRUD Trunks ──
POST   /api/v1/trunks                              → create_trunk()
GET    /api/v1/trunks?workspace_slug=alloy-finance  → list_trunks()
GET    /api/v1/trunks/{trunk_id}                    → get_trunk()
PATCH  /api/v1/trunks/{trunk_id}                    → update_trunk()
DELETE /api/v1/trunks/{trunk_id}                    → delete_trunk()

# ── Rutas (solo mode=inbound) ──
POST   /api/v1/trunks/{trunk_id}/routes             → add_route()
DELETE /api/v1/trunks/{trunk_id}/routes/{extension}  → remove_route()

# ── Credenciales ──
POST   /api/v1/trunks/{trunk_id}/rotate-credentials  → rotate_credentials()

# ── Llamadas salientes (solo mode=outbound) ──
POST   /api/v1/trunks/{trunk_id}/calls              → originate_call()
GET    /api/v1/trunks/{trunk_id}/calls              → list_calls()
GET    /api/v1/trunks/{trunk_id}/calls/{call_id}    → get_call()
DELETE /api/v1/trunks/{trunk_id}/calls/{call_id}    → cancel_call()
```

Cada endpoint:
- Usa schemas de `trunks.py` como request/response models
- Genera `_links` HATEOAS con helper `get_trunk_links(request, trunk_id)`
- Errores: 404 si trunk no existe, 422 si validación falla, 409 si extensión duplicada, 429 si max_concurrent_calls alcanzado
- Documentado para Swagger
- Los endpoints de `/calls` validan que el trunk sea mode=outbound (400 si no)

### 4. Modificar: `app/api/v1/__init__.py` (2 líneas)

```python
from app.api.v1.trunks import router as trunks_router
# ... routers existentes ...
router.include_router(trunks_router, prefix="/trunks", tags=["SIP Trunks"])
```

### 5. Modificar: `app/main.py` (agregar tag en openapi_tags)

```python
{
    "name": "SIP Trunks",
    "description": "Gestion de SIP Trunks para conectar PBXes externas a agentes de voz. "
                   "Soporta modo inbound (la PBX te llama) y register (te registras en la PBX del cliente).",
},
```

### 6. NO se modifica (por ahora)

- **`deployment_service.py` / `deployments.py`** — Modelo viejo SIP-por-agente queda como está. Se puede deprecar después.
- **`agent_pipeline_engine.py`** — Pipeline no cambia. El SIP Bridge (fase futura) usará `resolve_inbound_call()` / `resolve_register_call()`.
- **`config.py`** — Settings de Asterisk AMI se agregan cuando se implemente el SIP Bridge.
- **`compose.yaml`** — Asterisk se agrega cuando se implemente el SIP Bridge.

## Orden de Implementación

| Paso | Archivo | Acción | Dependencia |
|------|---------|--------|-------------|
| 1 | `app/schemas/trunks.py` | Crear | Ninguna |
| 2 | `app/services/trunk_service.py` | Crear | Paso 1 |
| 3 | `app/api/v1/trunks.py` | Crear | Pasos 1, 2 |
| 4 | `app/api/v1/__init__.py` | Modificar (2 líneas) | Paso 3 |
| 5 | `app/main.py` | Modificar (tag OpenAPI) | Paso 4 |

## Ejemplos de Uso

### Ejemplo 1: Trunk Inbound (call center)

```bash
# 1. Crear trunk inbound
POST /api/v1/trunks
{
  "name": "Trunk Principal Alloy",
  "tenant_id": "tenant-abc",
  "workspace_slug": "alloy-finance",
  "mode": "inbound",
  "inbound_auth": {
    "auth_type": "digest",
    "username": "alloy-trunk"
  },
  "max_concurrent_calls": 10,
  "codecs": ["ulaw", "alaw"]
}

# → Response 201:
{
  "trunk_id": "trk_a1b2c3d4e5f6",
  "name": "Trunk Principal Alloy",
  "mode": "inbound",
  "sip_host": "alloy-finance.sip.tito.ai",
  "sip_port": 5060,
  "inbound_auth": {
    "auth_type": "digest",
    "username": "alloy-trunk",
    "password": "xK9mP2vL8nQ3"
  },
  "routes": [],
  "status": "active",
  "_links": {
    "self": {"href": "/api/v1/trunks/trk_a1b2c3d4e5f6", "method": "GET"},
    "routes": {"href": "/api/v1/trunks/trk_a1b2c3d4e5f6/routes", "method": "POST"},
    "rotate": {"href": "/api/v1/trunks/trk_a1b2c3d4e5f6/rotate-credentials", "method": "POST"},
    "delete": {"href": "/api/v1/trunks/trk_a1b2c3d4e5f6", "method": "DELETE"}
  }
}

# 2. Agregar rutas
POST /api/v1/trunks/trk_a1b2c3d4e5f6/routes
{"extension": "100", "agent_id": "luna-soporte"}

POST /api/v1/trunks/trk_a1b2c3d4e5f6/routes
{"extension": "200", "agent_id": "ventas-bot"}

# 3. Admin configura su PBX:
#    Trunk → alloy-finance.sip.tito.ai:5060 (user: alloy-trunk, pass: xK9mP2vL8nQ3)
#    Ext 100 → Dial(PJSIP/tito-trunk/100)
#    Ext 200 → Dial(PJSIP/tito-trunk/200)
```

### Ejemplo 2: Register (PyME con 3CX)

```bash
# 1. Crear trunk register
POST /api/v1/trunks
{
  "name": "Ext 100 en 3CX Alloy",
  "tenant_id": "tenant-abc",
  "workspace_slug": "alloy-finance",
  "mode": "register",
  "register": {
    "remote_host": "pbx.alloy.com",
    "remote_port": 5060,
    "username": "100",
    "password": "ext100pass",
    "transport": "udp",
    "register_interval": 120
  },
  "agent_id": "luna-soporte",
  "max_concurrent_calls": 1
}

# → Response 201:
{
  "trunk_id": "trk_x7y8z9w0a1b2",
  "name": "Ext 100 en 3CX Alloy",
  "mode": "register",
  "register": {
    "remote_host": "pbx.alloy.com",
    "remote_port": 5060,
    "username": "100",
    "password": "ext100pass",
    "transport": "udp",
    "register_interval": 120
  },
  "agent_id": "luna-soporte",
  "registration_status": "unregistered",
  "status": "active",
  "_links": { ... }
}

# 2. Tu Asterisk se registra automáticamente en pbx.alloy.com como ext 100
#    (esto pasa en el SIP Bridge, fase futura)

# 3. Consultar estado de registro:
GET /api/v1/trunks/trk_x7y8z9w0a1b2

# → registration_status: "registered" ✓

# 4. Alguien marca ext 100 en 3CX → PBX envía INVITE a tu Asterisk → Pipeline luna-soporte
```

### Ejemplo 3: Outbound (campaña de cobranza)

```bash
# 1. Crear trunk outbound con carrier Twilio
POST /api/v1/trunks
{
  "name": "Twilio Outbound - Cobranzas",
  "tenant_id": "tenant-abc",
  "workspace_slug": "alloy-finance",
  "mode": "outbound",
  "outbound": {
    "carrier_host": "sip.twilio.com",
    "carrier_port": 5060,
    "username": "ACxxxxxxxxxxxxx",
    "password": "auth_token_here",
    "transport": "tls",
    "caller_id": "+573001234567"
  },
  "max_concurrent_calls": 5,
  "codecs": ["ulaw", "opus"]
}

# → Response 201:
{
  "trunk_id": "trk_out_c3d4e5f6g7h8",
  "name": "Twilio Outbound - Cobranzas",
  "mode": "outbound",
  "outbound": {
    "carrier_host": "sip.twilio.com",
    "carrier_port": 5060,
    "username": "ACxxxxxxxxxxxxx",
    "password": "auth_token_here",
    "transport": "tls",
    "caller_id": "+573001234567"
  },
  "total_calls_made": 0,
  "active_calls": 0,
  "status": "active",
  "_links": {
    "self": {"href": "/api/v1/trunks/trk_out_c3d4e5f6g7h8", "method": "GET"},
    "calls": {"href": "/api/v1/trunks/trk_out_c3d4e5f6g7h8/calls", "method": "POST"},
    "rotate": {"href": "/api/v1/trunks/trk_out_c3d4e5f6g7h8/rotate-credentials", "method": "POST"},
    "delete": {"href": "/api/v1/trunks/trk_out_c3d4e5f6g7h8", "method": "DELETE"}
  }
}

# 2. Originar una llamada
POST /api/v1/trunks/trk_out_c3d4e5f6g7h8/calls
{
  "to": "+573109876543",
  "agent_id": "cobranzas-bot",
  "timeout_seconds": 25,
  "callback_url": "https://backend.alloy.com/webhooks/calls",
  "metadata": {
    "customer_name": "Juan Pérez",
    "debt_amount": 150000,
    "due_date": "2026-04-01",
    "account_id": "ACC-789"
  }
}

# → Response 201:
{
  "call_id": "call_m1n2o3p4q5r6",
  "trunk_id": "trk_out_c3d4e5f6g7h8",
  "agent_id": "cobranzas-bot",
  "to": "+573109876543",
  "caller_id": "+573001234567",
  "call_status": "queued",
  "session_id": null,
  "_links": {
    "self": {"href": "/api/v1/trunks/trk_out_c3d4e5f6g7h8/calls/call_m1n2o3p4q5r6", "method": "GET"},
    "cancel": {"href": "/api/v1/trunks/trk_out_c3d4e5f6g7h8/calls/call_m1n2o3p4q5r6", "method": "DELETE"}
  }
}

# 3. Consultar estado de la llamada
GET /api/v1/trunks/trk_out_c3d4e5f6g7h8/calls/call_m1n2o3p4q5r6

# → Cuando el usuario contesta:
{
  "call_id": "call_m1n2o3p4q5r6",
  "call_status": "answered",
  "session_id": "sess_a1b2c3d4e5f6",
  ...
}

# 4. Webhooks enviados al callback_url:
#    call.ringing  → El teléfono está sonando
#    call.answered → El usuario contestó, pipeline activo
#    call.completed → Llamada terminada normalmente
#    call.failed   → Error de conexión / número inválido
#    call.no_answer → Timeout, nadie contestó
#    call.busy     → Línea ocupada

# 5. Listar llamadas activas del trunk
GET /api/v1/trunks/trk_out_c3d4e5f6g7h8/calls

# 6. Cancelar una llamada en curso
DELETE /api/v1/trunks/trk_out_c3d4e5f6g7h8/calls/call_m1n2o3p4q5r6
```

**Flujo outbound con metadata inyectada al agente:**

El campo `metadata` del `OutboundCallRequest` se inyecta como contexto al LLM. Así el agente sabe con quién habla y por qué:

```
metadata: {"customer_name": "Juan Pérez", "debt_amount": 150000}
                        ↓
Se agrega al system prompt del agente:
  "Estás llamando a Juan Pérez. Tiene una deuda de $150,000 COP vencida desde 2026-04-01.
   Tu objetivo es negociar un plan de pago."
                        ↓
Agente: "Hola Juan, te llamo de Alloy Finance respecto a tu cuenta..."
```

## Diagrama de Flujo por Modo

```
MODE INBOUND:
  Admin PBX ──configura trunk──► PBX Cliente ──SIP INVITE──► Tu Asterisk
                                                                  │
                            trunk_service.resolve_inbound_call()  │
                            workspace="alloy", ext="100"          │
                                      │                           │
                                      ▼                           ▼
                              agent_id="luna"              AudioSocket → Pipeline


MODE REGISTER:
  POST /trunks (mode=register) → Tu Asterisk ──SIP REGISTER──► PBX Cliente
                                                                    │
                                 (ext 100 queda apuntando a ti)     │
                                                                    │
  Alguien marca 100 → PBX Cliente ──SIP INVITE──► Tu Asterisk      │
                                                        │           │
                          trunk_service.resolve_register_call()     │
                          trunk_id="trk_x7y8z9w0a1b2"              │
                                    │                               │
                                    ▼                               ▼
                            agent_id="luna"                AudioSocket → Pipeline


MODE OUTBOUND:
  POST /trunks/{id}/calls → trunk_service.originate_call()
                                      │
                                      ▼
                    Tu Asterisk ──SIP INVITE──► Carrier (Twilio/VoIP)
                                                      │
                                                      ▼
                                              Red Telefónica → Teléfono Usuario
                                                                    │
                                                              Usuario contesta
                                                                    │
                            Carrier ──RTP──► Tu Asterisk            │
                                                │                   │
                                          AudioSocket → Pipeline    │
                                                │                   │
                                    Agente IA habla con el usuario  │
                                                                    │
                            Webhooks: call.ringing → call.answered → call.completed
```

## Webhooks por Modo

| Evento | Inbound | Register | Outbound |
|--------|---------|----------|----------|
| `session.started` | Si | Si | Si (cuando call_status=answered) |
| `session.ended` | Si | Si | Si |
| `session.error` | Si | Si | Si |
| `call.ringing` | No | No | **Si** |
| `call.answered` | No | No | **Si** |
| `call.completed` | No | No | **Si** |
| `call.failed` | No | No | **Si** |
| `call.no_answer` | No | No | **Si** |
| `call.busy` | No | No | **Si** |
