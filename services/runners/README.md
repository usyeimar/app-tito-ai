# Tito AI Runners

Servicio backend en **FastAPI** que orquesta agentes conversacionales de voz en tiempo real usando [Pipecat](https://github.com/pipecat-ai/pipecat). Gestiona sesiones de voz completas: crea salas WebRTC, genera tokens, lanza pipelines de IA (STT -> LLM -> TTS) y expone eventos en tiempo real vía WebSocket y webhooks.

---

## Tabla de Contenidos

1. [Arquitectura General](#arquitectura-general)
2. [Requisitos Previos](#requisitos-previos)
3. [Instalacion Rapida](#instalacion-rapida)
4. [Variables de Entorno](#variables-de-entorno)
5. [Ejecutar el Servicio](#ejecutar-el-servicio)
6. [Documentacion Swagger (OpenAPI)](#documentacion-swagger-openapi)
7. [API Reference](#api-reference)
8. [WebSocket Events](#websocket-events)
9. [Webhooks](#webhooks)
10. [Ejemplo Completo de Uso](#ejemplo-completo-de-uso)
11. [Docker](#docker)
12. [Estructura del Proyecto](#estructura-del-proyecto)

---

## Arquitectura General

```
                    +-------------------+
                    |   Cliente Web /   |
                    |   Mobile App      |
                    +--------+----------+
                             |
                    POST /api/v1/sessions
                             |
                    +--------v----------+
                    |   FastAPI Runner   |
                    |   (este servicio)  |
                    +--------+----------+
                             |
              +--------------+--------------+
              |                             |
     +--------v--------+          +--------v--------+
     |   Daily.co API  |    OR    |  LiveKit Cloud  |
     |  (WebRTC rooms) |          |  (WebRTC rooms) |
     +-----------------+          +-----------------+
              |                             |
              +-------------+---------------+
                            |
                   +--------v--------+
                   |  Pipecat Pipeline|
                   |  STT -> LLM -> TTS|
                   +-----------------+
                            |
                   +--------v--------+
                   |     Redis       |
                   | (sessions +     |
                   |  pub/sub events)|
                   +-----------------+
```

**Flujo resumido:**
1. El cliente envía un `POST /api/v1/sessions` con la configuracion del agente (`AgentConfig`).
2. El runner crea una sala WebRTC en Daily o LiveKit, genera tokens de acceso.
3. Lanza un pipeline Pipecat en background (STT -> LLM -> TTS).
4. Devuelve `session_id`, `url` y `access_token` para que el cliente se conecte a la sala.
5. El cliente se une a la sala con el token y habla con el agente de voz.
6. Eventos de transcripcion se emiten via WebSocket y webhooks.

---

## Requisitos Previos

| Herramienta | Version   | Proposito                          |
|-------------|-----------|-------------------------------------|
| Python      | 3.11-3.12 | Runtime                             |
| uv          | latest    | Gestor de paquetes (recomendado)    |
| Redis       | 6+        | Sesiones + Pub/Sub de eventos       |
| Docker      | 20+       | Despliegue (opcional)               |

**Cuentas de servicios necesarias (al menos una de cada):**

- **Transporte WebRTC:** [Daily.co](https://daily.co) o [LiveKit](https://livekit.io)
- **STT (Speech-to-Text):** [Deepgram](https://deepgram.com), Google, etc.
- **LLM:** [OpenAI](https://openai.com), Anthropic, Google, Groq, Together, Mistral, etc.
- **TTS (Text-to-Speech):** [Cartesia](https://cartesia.ai), ElevenLabs, Deepgram, PlayHT, Azure, etc.

---

## Instalacion Rapida

```bash
# 1. Clonar el repositorio
git clone <tu-repo-url>
cd services/runners

# 2. Copiar variables de entorno
cp .env.example .env
# Edita .env con tus claves reales (ver seccion Variables de Entorno)

# 3. Instalar dependencias con uv
uv sync

# 4. Iniciar Redis (si no lo tienes corriendo)
docker run -d --name redis -p 6379:6379 redis:7-alpine

# 5. Arrancar el servidor
uv run uvicorn app.main:app --reload --host 0.0.0.0 --port 8000
```

El servidor estara disponible en `http://localhost:8000`.

---

## Variables de Entorno

Crea un archivo `.env` en la raiz del proyecto basandote en `.env.example`:

```env
# === GENERAL ===
PROJECT_NAME="Tito AI Runners"

# === TRANSPORTE WebRTC (elige uno o ambos) ===
# Daily.co
DAILY_API_KEY=tu_daily_api_key
DAILY_API_URL=https://api.daily.co/v1

# LiveKit
LIVEKIT_URL=wss://tu-proyecto.livekit.cloud
LIVEKIT_API_KEY=tu_livekit_api_key
LIVEKIT_API_SECRET=tu_livekit_api_secret

# === LLM ===
OPENAI_API_KEY=sk-xxxxxxxxxxxxxxxx
GOOGLE_API_KEY=AIzaSyxxxxxxxxxx
# (agrega las API keys de otros proveedores segun necesites)

# === STT ===
DEEPGRAM_API_KEY=tu_deepgram_key

# === TTS ===
CARTESIA_API_KEY=sk_car_xxxxxxxxxx
ELEVENLABS_API_KEY=tu_elevenlabs_key

# === REDIS ===
REDIS_URL=redis://localhost:6379/0

# === BACKEND (webhooks a Laravel) ===
BACKEND_URL=http://localhost:8000
BACKEND_API_KEY=tu_backend_api_key

# === SCALING ===
MAX_CONCURRENT_SESSIONS=10
```

| Variable                   | Requerida | Default                  | Descripcion                                      |
|----------------------------|-----------|--------------------------|--------------------------------------------------|
| `PROJECT_NAME`             | No        | `Tito AI Runners`        | Nombre del proyecto (aparece en Swagger)          |
| `DAILY_API_KEY`            | Si*       | -                        | API Key de Daily.co (*si usas Daily)              |
| `DAILY_API_URL`            | No        | `https://api.daily.co/v1`| URL base de la API de Daily                       |
| `LIVEKIT_URL`              | Si*       | -                        | URL WebSocket de LiveKit (*si usas LiveKit)       |
| `LIVEKIT_API_KEY`          | Si*       | -                        | API Key de LiveKit                                |
| `LIVEKIT_API_SECRET`       | Si*       | -                        | API Secret de LiveKit                             |
| `OPENAI_API_KEY`           | Si*       | -                        | API Key de OpenAI (*si usas OpenAI como LLM)      |
| `DEEPGRAM_API_KEY`         | Si*       | -                        | API Key de Deepgram (*si usas Deepgram como STT)  |
| `CARTESIA_API_KEY`         | Si*       | -                        | API Key de Cartesia (*si usas Cartesia como TTS)  |
| `REDIS_URL`                | Si        | `redis://localhost:6379/0`| URL de conexion a Redis                           |
| `BACKEND_URL`              | No        | `http://localhost:8000`  | URL del backend Laravel para webhooks             |
| `BACKEND_API_KEY`          | No        | -                        | API Key para autenticar webhooks                  |
| `MAX_CONCURRENT_SESSIONS`  | No        | `10`                     | Maximo de sesiones simultaneas por runner          |
| `DEFAULT_TRANSPORT_PROVIDER`| No       | `daily`                  | Proveedor de transporte por defecto               |

---

## Ejecutar el Servicio

### Desarrollo local

```bash
uv run uvicorn app.main:app --reload --host 0.0.0.0 --port 8000
```

### Docker

```bash
docker compose up --build
```

### Verificar que funciona

```bash
curl http://localhost:8000/health
```

Respuesta esperada:
```json
{
  "status": "ok",
  "active_sessions": 0,
  "max_sessions": 10,
  "at_capacity": false,
  "host_id": "runner-a1b2c3d4"
}
```

---

## Documentacion Swagger (OpenAPI)

Una vez arrancado el servidor, accede a:

| URL                                        | Descripcion                                |
|--------------------------------------------|--------------------------------------------|
| `http://localhost:8000/docs`               | **Swagger UI** - Interfaz interactiva       |
| `http://localhost:8000/redoc`              | **ReDoc** - Documentacion alternativa       |
| `http://localhost:8000/api/v1/openapi.json`| **OpenAPI JSON** - Esquema raw             |

La documentacion Swagger se genera automaticamente desde los modelos Pydantic y las anotaciones de FastAPI. Incluye:
- Todos los endpoints con sus schemas de request/response
- Modelos de error estandarizados (`APIErrorResponse`)
- Ejemplos en cada modelo
- Codigos de respuesta documentados (201, 400, 404, 422, 500, 503)

---

## API Reference

### Base URL

```
http://localhost:8000/api/v1
```

---

### `GET /health`

Health check para Kubernetes / Load Balancers.

**Response `200 OK`:**

```json
{
  "status": "ok",
  "active_sessions": 2,
  "max_sessions": 10,
  "at_capacity": false,
  "host_id": "runner-a1b2c3d4"
}
```

---

### `GET /api/v1/status`

Verifica que la API v1 esta activa.

**Response `200 OK`:**

```json
{
  "message": "API v1 is running"
}
```

---

### `POST /api/v1/sessions`

Crea una nueva sesion de agente de voz. Este es el endpoint principal.

**Request Body** (`AgentConfig`):

```json
{
  "version": "1.0.0",
  "agent_id": "alloy-mvp-001",
  "tenant_id": "tenant-abc-123",
  "callback_url": "https://tu-backend.com/api/webhooks/tito",
  "metadata": {
    "name": "Luna - Soporte",
    "slug": "luna-soporte-v1",
    "description": "Agente de soporte al cliente",
    "tags": ["support", "spanish"],
    "language": "es"
  },
  "brain": {
    "llm": {
      "provider": "openai",
      "model": "gpt-4o-mini",
      "config": {
        "temperature": 0.5,
        "max_tokens": 1024
      },
      "instructions": "Eres Luna, asistente de soporte. Responde en espanol."
    },
    "localization": {
      "default_locale": "es-CO",
      "timezone": "America/Bogota",
      "currency": "COP",
      "number_format": "dot_decimal"
    }
  },
  "runtime_profiles": {
    "stt": {
      "provider": "deepgram",
      "model": "nova-2",
      "language": "es"
    },
    "tts": {
      "provider": "cartesia",
      "voice_id": "79a125e8-cd45-4c13-8a67-188112f4dd22"
    },
    "transport": {
      "provider": "daily"
    },
    "behavior": {
      "interruptibility": true,
      "initial_action": "SPEAK_FIRST",
      "streaming": true
    },
    "session_limits": {
      "inactivity_timeout": {
        "enabled": true,
        "steps": [
          { "wait_seconds": 15, "message": ["Sigues ahi?"] }
        ],
        "final_message": "Cierro por inactividad."
      },
      "max_duration_seconds": 600
    }
  },
  "capabilities": {
    "tools": [
      {
        "name": "get_weather",
        "description": "Obtiene el clima actual",
        "parameters": {
          "type": "object",
          "properties": {
            "city": { "type": "string" }
          },
          "required": ["city"]
        }
      }
    ]
  }
}
```

**Response `201 Created`** (`SessionResponse`):

```json
{
  "session_id": "sess_a1b2c3d4e5f6",
  "room_name": "tito_alloy-mvp-001_f7e8d9c0",
  "provider": "daily",
  "url": "https://your-domain.daily.co/tito_alloy-mvp-001_f7e8d9c0",
  "access_token": "eyJhbGciOiJIUzI1NiIs...",
  "context": {
    "agent_id": "alloy-mvp-001",
    "tenant_id": "tenant-abc-123",
    "created_at": 1712444800.0,
    "expires_at": 1712448400.0
  }
}
```

**Errores posibles:**

| Codigo | Situacion                                             |
|--------|-------------------------------------------------------|
| `422`  | Payload invalido (campos requeridos faltantes)        |
| `503`  | Runner al maximo de capacidad (`Retry-After: 30`)     |
| `503`  | Fallo al crear sala en Daily/LiveKit                  |
| `500`  | Error interno al inicializar el pipeline              |

**Ejemplo con cURL:**

```bash
curl -X POST http://localhost:8000/api/v1/sessions \
  -H "Content-Type: application/json" \
  -d '{
    "version": "1.0.0",
    "agent_id": "test-agent-001",
    "tenant_id": "tenant-123",
    "metadata": {
      "name": "Test Agent",
      "slug": "test-agent",
      "description": "Agente de prueba",
      "tags": ["test"],
      "language": "es"
    },
    "brain": {
      "llm": {
        "provider": "openai",
        "model": "gpt-4o-mini",
        "instructions": "Eres un agente de prueba. Responde brevemente en espanol."
      }
    },
    "runtime_profiles": {
      "stt": { "provider": "deepgram", "model": "nova-2" },
      "tts": { "provider": "cartesia", "voice_id": "79a125e8-cd45-4c13-8a67-188112f4dd22" }
    }
  }'
```

---

### `GET /api/v1/sessions`

Lista las sesiones activas en este runner.

**Response `200 OK`** (`SessionListResponse`):

```json
{
  "sessions": [],
  "count": 3,
  "status": "operational"
}
```

---

### `DELETE /api/v1/sessions/{session_id}`

Termina una sesion de agente de forma inmediata.

**Path Parameters:**

| Parametro    | Tipo   | Descripcion                    |
|--------------|--------|--------------------------------|
| `session_id` | string | ID de la sesion (ej: `sess_a1b2c3d4e5f6`) |

**Response `200 OK`** (`ActionResponse`):

```json
{
  "success": true,
  "message": "Sesion sess_a1b2c3d4e5f6 terminada exitosamente."
}
```

**Errores:**

| Codigo | Situacion                |
|--------|--------------------------|
| `404`  | Sesion no encontrada     |

**Ejemplo con cURL:**

```bash
curl -X DELETE http://localhost:8000/api/v1/sessions/sess_a1b2c3d4e5f6
```

---

### `GET /api/v1/metrics` (oculto en Swagger)

Endpoint de metricas Prometheus. No aparece en Swagger (`include_in_schema=False`).

```bash
curl http://localhost:8000/api/v1/metrics
```

Metricas expuestas:

| Metrica                          | Tipo      | Descripcion                            |
|----------------------------------|-----------|----------------------------------------|
| `tito_active_sessions`           | Gauge     | Sesiones activas en este runner        |
| `tito_dropped_frames_total`      | Counter   | Frames de audio perdidos               |
| `tito_session_duration_seconds`  | Histogram | Duracion de sesiones (buckets: 30s-1h) |
| `tito_session_errors_total`      | Counter   | Sesiones terminadas con error          |

---

## WebSocket Events

### Conectarse

```
ws://localhost:8000/api/v1/sessions/{session_id}/ws
```

El WebSocket se suscribe a los eventos de una sesion en tiempo real via Redis Pub/Sub.

### Eventos emitidos

**Transcripcion del usuario:**
```json
{
  "event": "transcript.user",
  "session_id": "sess_a1b2c3d4e5f6",
  "text": "Hola, necesito ayuda",
  "is_final": true
}
```

**Transcripcion del agente:**
```json
{
  "event": "transcript.agent",
  "session_id": "sess_a1b2c3d4e5f6",
  "text": "Hola! En que puedo ayudarte?"
}
```

**Shutdown del servidor:**
```json
{
  "event": "server.shutdown",
  "message": "Server is shutting down"
}
```

---

## Webhooks

Si proporcionas `callback_url` en la configuracion del agente, el runner enviara eventos HTTP POST a esa URL.

### Headers

```
Content-Type: application/json
X-Tito-Agent-Key: <BACKEND_API_KEY>
```

### Payload

```json
{
  "event": "session.started",
  "agent_id": "alloy-mvp-001",
  "tenant_id": "tenant-abc-123",
  "room_name": "https://your-domain.daily.co/room-name",
  "timestamp": 1712444800.0,
  "data": {
    "session_id": "sess_a1b2c3d4e5f6",
    "agent_id": "alloy-mvp-001"
  }
}
```

### Tipos de evento

| Evento            | Cuando se emite                              |
|-------------------|----------------------------------------------|
| `session.started` | El pipeline arranco y esta listo              |
| `session.ended`   | La sesion termino (incluye transcripciones)   |
| `session.error`   | El pipeline fallo con un error                |

### Payload de `session.ended`

```json
{
  "event": "session.ended",
  "data": {
    "session_id": "sess_a1b2c3d4e5f6",
    "status": "completed",
    "duration": 125.4,
    "transcription": [
      { "role": "user", "content": "Hola" },
      { "role": "assistant", "content": "Hola! En que puedo ayudarte?" }
    ]
  }
}
```

---

## Ejemplo Completo de Uso

### 1. Crear una sesion

```bash
# Crear sesion de voz
RESPONSE=$(curl -s -X POST http://localhost:8000/api/v1/sessions \
  -H "Content-Type: application/json" \
  -d @docs/agent_mvp_manifest.json)

echo $RESPONSE | python -m json.tool

# Extraer datos de conexion
SESSION_ID=$(echo $RESPONSE | python -c "import sys,json; print(json.load(sys.stdin)['session_id'])")
ROOM_URL=$(echo $RESPONSE | python -c "import sys,json; print(json.load(sys.stdin)['url'])")
TOKEN=$(echo $RESPONSE | python -c "import sys,json; print(json.load(sys.stdin)['access_token'])")

echo "Session: $SESSION_ID"
echo "Room URL: $ROOM_URL"
echo "Token: $TOKEN"
```

### 2. Conectarse al WebSocket de eventos

```bash
# En otra terminal (requiere websocat: https://github.com/nickel-org/websocat)
websocat ws://localhost:8000/api/v1/sessions/$SESSION_ID/ws
```

### 3. Unirse a la sala de voz

Usa el `ROOM_URL` y `TOKEN` en tu cliente web/mobile. Si usas Daily.co:

```javascript
// Frontend con Daily.co
import DailyIframe from '@daily-co/daily-js';

const callFrame = DailyIframe.createFrame();
await callFrame.join({ url: ROOM_URL, token: TOKEN });
```

Si usas LiveKit:

```javascript
// Frontend con LiveKit
import { Room } from 'livekit-client';

const room = new Room();
await room.connect(ROOM_URL, TOKEN);
```

### 4. Terminar la sesion

```bash
curl -X DELETE http://localhost:8000/api/v1/sessions/$SESSION_ID
```

---

## Docker

### Build y ejecutar

```bash
docker compose up --build
```

### Solo build

```bash
docker build -t tito-runner .
docker run -p 8000:8000 --env-file .env tito-runner
```

El `compose.yaml` monta `./app` como volumen para hot-reload en desarrollo.

---

## Estructura del Proyecto

```
runners/
  .env                          # Variables de entorno (NO commitear)
  .env.example                  # Plantilla de variables
  pyproject.toml                # Dependencias y config del proyecto
  compose.yaml                  # Docker Compose
  Dockerfile                    # Imagen de produccion
  app/
    main.py                     # Entry point FastAPI + lifespan
    core/
      config.py                 # Settings (pydantic_settings)
      errors.py                 # Exception handlers globales
      logger.py                 # Configuracion de Loguru
    api/
      v1/
        __init__.py             # Router principal (sessions + metrics)
        api.py                  # Re-export del router
        sessions.py             # Endpoints CRUD de sesiones
        ws.py                   # Shim de compatibilidad WebSocket
        metrics.py              # Endpoint Prometheus /metrics
    schemas/
      agent.py                  # AgentConfig y sub-modelos (Pydantic)
      sessions.py               # SessionResponse, ActionResponse, etc.
      errors.py                 # APIErrorResponse estandarizado
    services/
      session_manager.py        # Redis-backed session state + WS pub/sub
      task_manager.py           # AsyncIO task lifecycle manager
      livekit_service.py        # Integracion LiveKit (rooms + tokens)
      daily_service.py          # Integracion Daily.co (rooms + tokens)
      webhook_service.py        # HTTP POST a callback_url
      agents/
        runner.py               # (Legacy) runner base
        factory/
          builder.py            # ServiceFactory: crea STT/LLM/TTS
          providers.py          # Mapeo de proveedores soportados
          helpers.py            # Utilidades de factory
        pipelines/
          agent_pipeline_engine.py  # Orquestador principal del pipeline
          transport_setup.py        # Setup de Daily/LiveKit transport
          context_setup.py          # Setup del contexto LLM
          pipeline_builder.py       # Construye el pipeline Pipecat
        tools/
          agent_tools.py        # Function calling tools del agente
        prompts/
          system_prompts.py     # Templates de prompts
  docs/
    agent_mvp_manifest.json     # Ejemplo de AgentConfig minimo
    agent_unified_manifest.json # Ejemplo de AgentConfig completo
  tests/
    test_service_factory.py
    test_worker.py
```

---

## Modelo de Datos: AgentConfig

El `AgentConfig` es el corazon de la API. Define completamente como se comporta un agente de voz. Aqui estan sus secciones principales:

| Seccion            | Descripcion                                                    |
|--------------------|----------------------------------------------------------------|
| `metadata`         | Nombre, slug, descripcion, idioma y tags del agente            |
| `brain.llm`        | Proveedor LLM, modelo, temperatura, instrucciones (prompt)    |
| `brain.localization`| Locale, timezone, moneda                                      |
| `brain.context`    | Estrategia de manejo de contexto (summarize/truncate/none)     |
| `runtime_profiles.stt` | Proveedor y modelo de Speech-to-Text                      |
| `runtime_profiles.tts` | Proveedor, voice_id y velocidad de Text-to-Speech          |
| `runtime_profiles.vad` | Voice Activity Detection (silero)                          |
| `runtime_profiles.transport` | Proveedor WebRTC (daily/livekit)                      |
| `runtime_profiles.behavior`  | Interrumpibilidad, accion inicial, sonidos             |
| `runtime_profiles.session_limits` | Timeout por inactividad, duracion maxima          |
| `capabilities.tools` | Function calling tools disponibles para el agente           |
| `orchestration`    | Routing y contexto de sesion                                   |
| `compliance`       | PII redaction, grabacion de audio                              |
| `observability`    | Nivel de log, metricas                                         |

### Proveedores Soportados

| Componente | Proveedores                                                        |
|------------|--------------------------------------------------------------------|
| **LLM**    | OpenAI, Anthropic, Google, Groq, Together, Mistral                 |
| **STT**    | Deepgram, Google, Gladia, AssemblyAI, AWS, Ultravox                |
| **TTS**    | Cartesia, ElevenLabs, Deepgram, PlayHT, Azure, Google              |
| **WebRTC** | Daily.co, LiveKit                                                  |
| **VAD**    | Silero                                                             |

---

## Codigos de Error

Todos los errores siguen el formato `APIErrorResponse`:

```json
{
  "error": {
    "status": 422,
    "code": "VALIDATION_ERROR",
    "title": "Validation failed for the request payload.",
    "docs_url": "https://api.tito.ai/docs/errors#VALIDATION_ERROR",
    "details": [
      {
        "code": "INVALID_ATTRIBUTE",
        "title": "field required",
        "source": { "pointer": "/body/brain/llm/provider" }
      }
    ]
  },
  "_links": {
    "docs": { "href": "/docs", "method": "GET" }
  }
}
```

| Codigo | Code                    | Cuando                                         |
|--------|-------------------------|-------------------------------------------------|
| `400`  | `BAD_REQUEST`           | Request malformado                              |
| `401`  | `UNAUTHORIZED`          | Sin autenticacion                               |
| `403`  | `FORBIDDEN`             | Sin permisos                                    |
| `404`  | `NOT_FOUND`             | Sesion no existe                                |
| `422`  | `VALIDATION_ERROR`      | Campos requeridos faltantes o tipos invalidos    |
| `500`  | `INTERNAL_SERVER_ERROR` | Error inesperado del servidor                   |
| `503`  | `SERVICE_UNAVAILABLE`   | Runner lleno o transporte no disponible          |
