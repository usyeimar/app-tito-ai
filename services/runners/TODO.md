# TODO - Tito AI Runners

## Proximas funcionalidades

### 1. Canales de despliegue de agentes

Actualmente los agentes solo se despliegan via API REST (`POST /api/v1/sessions`).
El objetivo es soportar multiples canales de acceso para que un mismo agente pueda
ser consumido de distintas formas sin cambiar su configuracion.

---

### 1.1 Web Widget (Embeddable)

**Descripcion:** Widget JavaScript que se incrusta en cualquier sitio web con un snippet
`<script>` y permite al usuario hablar con el agente directamente desde el navegador.

**Tareas:**

- [ ] Endpoint `POST /api/v1/agents/{agent_id}/widget` que devuelve la config del widget (tema, posicion, idioma)
- [ ] Endpoint `GET /api/v1/widget/{agent_id}/embed.js` que sirve el script embeddable
- [ ] El widget internamente llama a `POST /api/v1/sessions` para crear la sesion
- [ ] Manejo de permisos de microfono del navegador (MediaDevices API)
- [ ] Integracion con Daily.co Prebuilt o LiveKit Components para el frontend WebRTC
- [ ] Configuracion visual del widget por agente:
  - Color primario, logo, posicion (bottom-right, bottom-left)
  - Mensaje de bienvenida, texto del boton
  - Idioma de la UI
- [ ] Autenticacion por dominio (CORS allowlist por tenant)
- [ ] Rate limiting por widget/tenant
- [ ] Esquema Pydantic `WidgetConfig` con las opciones de personalizacion
- [ ] Pagina de preview `/widget/{agent_id}/preview` para probar antes de publicar

**Ejemplo de uso:**

```html
<!-- Pegar en cualquier sitio web -->
<script src="https://runners.tito.ai/api/v1/widget/agent-001/embed.js"></script>
```

---

### 1.2 Direct SIP (Session Initiation Protocol)

**Descripcion:** Generar una URI SIP por agente para que sistemas de telefonia (PBX, call centers,
Twilio, Asterisk, FreeSWITCH) puedan llamar al agente como si fuera una extension telefonica.

**Tareas:**

- [ ] Endpoint `POST /api/v1/agents/{agent_id}/sip` para provisionar una URI SIP del agente
  - Request:
    ```json
    {
      "agent_id": "agent-001",
      "tenant_id": "tenant-abc",
      "workspace_slug": "alloy-finance",
      "sip_provider": "livekit|daily|twilio",
      "api_key": "sk_sip_xxxxxxxxxxxxxxxx"
    }
    ```
  - Response:
    ```json
    {
      "sip_uri": "sip:agent-001@alloy-finance.sip.tito.ai",
      "sip_username": "agent-001",
      "sip_password": "auto-generated-password",
      "api_key": "sk_sip_xxxxxxxxxxxxxxxx",
      "workspace_subdomain": "alloy-finance.sip.tito.ai",
      "status": "active"
    }
    ```
- [ ] Endpoint `GET /api/v1/agents/{agent_id}/sip` para consultar la URI SIP activa
- [ ] Endpoint `DELETE /api/v1/agents/{agent_id}/sip` para desactivar/eliminar la URI SIP

**Subdominio SIP por workspace:**

Cada workspace/tenant tiene su propio subdominio SIP. Esto permite:
- Aislamiento entre organizaciones
- DNS routing por tenant
- Certificados TLS por subdominio (SIP over TLS / SRTP)

Formato: `sip:<agent_id>@<workspace_slug>.sip.tito.ai`

Ejemplos:
- `sip:luna-soporte@alloy-finance.sip.tito.ai`
- `sip:ventas-bot@acme-corp.sip.tito.ai`
- `sip:support-en@globex.sip.tito.ai`

**Autenticacion SIP:**

Toda llamada SIP entrante debe autenticarse con un API Key. Dos mecanismos soportados:
- **SIP Digest Auth**: username + password (generados al provisionar)
- **Header API Key**: Header custom `X-Tito-SIP-Key: sk_sip_xxxxxxxx` para integraciones programaticas
- El API Key se genera por agente al momento de provisionar y se puede rotar via `POST /api/v1/agents/{agent_id}/sip/rotate-key`

Tareas de autenticacion:
- [ ] Generacion de API Key unica por agente SIP (`sk_sip_` prefix)
- [ ] Endpoint `POST /api/v1/agents/{agent_id}/sip/rotate-key` para rotar el API Key
- [ ] Validacion de credenciales en el SIP bridge antes de conectar al runner
- [ ] Rate limiting por API Key (max llamadas concurrentes por agente)
- [ ] Logging de intentos fallidos de autenticacion

**Tareas de infraestructura:**

- [ ] Integracion con LiveKit SIP Bridge:
  - Crear SIP Trunk via LiveKit API
  - Crear SIP Dispatch Rule que enrute llamadas entrantes al agente correcto
  - Configurar `SIPInboundTrunkInfo` con credenciales y subdominio
- [ ] Integracion con Daily.co SIP (si lo soportan)
- [ ] Integracion con Twilio SIP Trunking como alternativa
- [ ] DNS wildcard `*.sip.tito.ai` apuntando al SIP bridge
- [ ] Certificado TLS wildcard para `*.sip.tito.ai` (SIP over TLS)
- [ ] Cuando llega una llamada SIP:
  1. El SIP bridge valida credenciales / API Key
  2. Resuelve el workspace desde el subdominio del SIP URI
  3. Busca la config del agente por `agent_id` + `workspace`
  4. Crea un participante en la sala WebRTC
  5. El runner detecta al participante y lanza el pipeline
  6. El audio fluye bidireccionalmente: telefono <-> STT -> LLM -> TTS
- [ ] Esquemas Pydantic: `SIPProvisionRequest`, `SIPProvisionResponse`, `SIPRotateKeyResponse`
- [ ] Persistencia de SIP trunks activos en Redis/DB
- [ ] Soporte para DTMF (tonos de marcacion) como input al agente
- [ ] Webhook `sip.call.incoming` al backend cuando entra una llamada
- [ ] Manejo de codecs de audio (PCMU/PCMA/Opus) segun el trunk

**Ejemplo de flujo completo:**

```
1. Admin provisiona SIP para su agente (workspace: alloy-finance):
   POST /api/v1/agents/luna-soporte/sip
   {
     "workspace_slug": "alloy-finance",
     "sip_provider": "livekit",
     "api_key": "sk_sip_abc123..."
   }
   -> {
        "sip_uri": "sip:luna-soporte@alloy-finance.sip.tito.ai",
        "api_key": "sk_sip_abc123...",
        "workspace_subdomain": "alloy-finance.sip.tito.ai",
        "status": "active"
      }

2. Admin configura esa URI SIP en su PBX / Asterisk / Twilio
   con las credenciales devueltas

3. Un cliente marca el numero de soporte de Alloy Finance

4. El PBX envia SIP INVITE a alloy-finance.sip.tito.ai

5. El SIP bridge valida el API Key y resuelve el agente

6. Se crea una sala WebRTC y se lanza el pipeline

7. El cliente habla por telefono con Luna (el agente IA)
```

**Diagrama:**

```
  Telefono / PBX / Call Center
          |
    SIP INVITE + API Key
    sip:luna@alloy-finance.sip.tito.ai
          |
  +-------v------------------+
  |  DNS: *.sip.tito.ai      |
  +-------+------------------+
          |
  +-------v------------------+
  |  SIP Bridge (LiveKit)    |
  |  1. Validar API Key      |
  |  2. Resolver workspace   |
  |  3. Buscar agente config |
  +-------+------------------+
          |
    WebRTC participant
          |
  +-------v------------------+
  |  Tito Runner             |
  |  Pipeline:               |
  |  STT -> LLM -> TTS      |
  +-------------------------+
```

---

### 1.3 Modelo de canales unificado

Una vez implementados Widget y SIP, unificar bajo un concepto de "canales":

- [ ] Modelo `AgentChannel` con tipo (`api`, `widget`, `sip`, `whatsapp-future`)
- [ ] Endpoint `GET /api/v1/agents/{agent_id}/channels` para listar canales activos
- [ ] Cada canal tiene su propia config pero comparte el `AgentConfig` base
- [ ] Dashboard de canales por agente en el frontend admin

---

## 2. Seguridad y Autenticacion

Actualmente los endpoints estan abiertos sin ninguna proteccion.

- [ ] **Auth por API Key / JWT en todos los endpoints**
  - Header `Authorization: Bearer <token>` o `X-Tito-Key: sk_live_xxx`
  - Middleware FastAPI que valida el token contra el backend Laravel
  - Scopes por tenant: un tenant solo ve/controla sus propias sesiones
- [ ] **Rate limiting por tenant**
  - Limites configurables: max sesiones concurrentes, max sesiones/hora
  - Usar Redis + sliding window
  - Header `X-RateLimit-Remaining` en responses
- [ ] **CORS configurado por tenant/widget**
  - Allowlist de dominios por workspace para el widget embeddable
- [ ] **Sanitizacion de inputs**
  - Las `instructions` del LLM pueden contener prompt injection — validar/sanitizar
  - Los `callback_url` deben validarse (no permitir URLs internas / SSRF)

---

## 3. Llamadas Salientes (Outbound Dialing)

Hoy solo se soportan llamadas entrantes (el usuario llama al agente).
Falta la capacidad de que el agente inicie la llamada.

- [ ] Endpoint `POST /api/v1/agents/{agent_id}/call`
  ```json
  {
    "to": "+573001234567",
    "from": "+571234567890",
    "agent_config": { "..." },
    "callback_url": "https://backend.com/webhook"
  }
  ```
- [ ] Integracion con Twilio / SIP INVITE outbound via LiveKit
- [ ] Estado de llamada: `ringing` -> `in-progress` -> `completed` / `failed` / `no-answer`
- [ ] Webhook `call.answered`, `call.completed`, `call.failed`
- [ ] Retry logic configurable (reintentos si no contestan)
- [ ] Campanas masivas: `POST /api/v1/campaigns`
  - Lista de numeros + agent_config + horarios
  - Cola de llamadas con rate limit (max N llamadas simultaneas)
  - Dashboard de progreso: contestadas, no-answer, completadas, errores

---

## 4. Grabacion y Transcripciones

**Estado actual:**
- `compliance.record_audio` existe en el schema `AgentConfig`.
- Cuando es `true`, se crea un `AudioBufferProcessor` y se inserta en el pipeline.
- Se llama `audio_buffer.start_recording()` al unirse el primer participante.
- [x] **Guardado local implementado** en `_save_recording()` dentro de `agent_pipeline_engine.py`.
  - Guarda WAV (16kHz, 16-bit, mono) en `resources/data/recordings/`.
  - Formato: `session_{session_id}_{YYYYMMDD_HHMMSS}.wav`
  - Se incluye `recording_path` en el webhook `session.ended`.
- Las transcripciones se envian por webhook (`session.ended`) pero no se persisten.

**Lo que falta:**

- [ ] **Storage backend configurable (produccion)**
  - Actualmente solo guarda local — en produccion necesita S3/MinIO
  - Variables de entorno: `RECORDING_STORAGE=s3|minio|local`
  - `S3_BUCKET`, `S3_REGION`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`
  - Servicio `RecordingStorageService` con interfaz comun (local, s3, minio)
  - Subir con path `/{tenant_id}/{agent_id}/{session_id}.wav`
- [ ] **Endpoint para descargar grabaciones**
  - `GET /api/v1/sessions/{session_id}/recording`
  - Devuelve signed URL de S3 o stream directo si es local
  - Autenticado por tenant (solo el tenant dueno puede descargar)
- [ ] **Limpieza de grabaciones locales**
  - Cron o tarea que borra archivos locales despues de subirlos a S3
  - O TTL configurable para grabaciones locales (ej: 24h)
- [ ] **Persistencia de transcripciones**
  - Actualmente las transcripciones se envian por webhook al final pero no se persisten
  - Guardar en DB (PostgreSQL) o al menos en Redis con TTL largo
  - Endpoint `GET /api/v1/sessions/{session_id}/transcript`
  - Formato: lista de mensajes con timestamps, rol (user/agent), texto
- [ ] **PII Redaction**
  - Si `compliance.pii_redaction: true`, limpiar datos sensibles de transcripciones
  - Numeros de tarjeta, telefono, correo, nombres — antes de persistir/subir

---

## 5. Knowledge Base / RAG

El schema tiene `brain.knowledge_base.id` pero no esta implementado.

- [ ] Endpoint `POST /api/v1/knowledge-bases` para crear una KB
- [ ] Subir documentos (PDF, TXT, CSV, URLs) y procesarlos en chunks
- [ ] Vector store (Pinecone, Qdrant, pgvector, o ChromaDB)
- [ ] En el pipeline, antes del LLM: buscar contexto relevante y adjuntarlo al prompt
- [ ] `brain.knowledge_base.id` referencia a una KB existente

---

## 6. Transferencia a Humano (Escalation)

Cuando el agente no puede resolver, debe poder transferir a un agente humano.

- [ ] Tool `transfer_to_human` disponible para el LLM
  - El LLM decide cuando escalar basado en sus instrucciones
  - Metadata: razon de escalacion, resumen de la conversacion
- [ ] Webhook `session.transfer_requested` al backend
- [ ] Si es SIP: SIP REFER / blind transfer al numero del agente humano
- [ ] Si es WebRTC: notificar al frontend via WebSocket para que redirija
- [ ] Cola de espera con musica/mensaje mientras se conecta al humano

---

## 7. Observabilidad y Monitoreo

**Estado actual:**
- Prometheus parcialmente implementado en `app/api/v1/metrics.py`:
  - `tito_active_sessions` (Gauge) — funciona, se actualiza en cada scrape
  - `tito_session_duration_seconds` (Histogram, buckets 30s-1h) — funciona, se observa en `finally` del pipeline
  - `tito_session_errors_total` (Counter, label `reason`) — funciona, labels: `pipeline_error`, `cancelled`
  - `tito_dropped_frames_total` (Counter) — **declarada pero nunca se incrementa** (dead code)
- Endpoint `GET /api/v1/metrics` sirve a Prometheus (excluido de Swagger).
- Logging con `logging` stdlib + format basico a stdout. Loguru esta en dependencias pero no se usa consistentemente.
- **No hay tracing.** No hay OpenTelemetry. No hay metricas de latencia por componente.

**Lo que falta:**

### 7.1 Metricas Prometheus (completar)

- [ ] **Arreglar `tito_dropped_frames_total`** — incrementar en el pipeline cuando se detecten frames perdidos
- [ ] **Metricas de latencia por componente** (lo mas importante para optimizar voz):
  ```python
  # En metrics.py agregar:
  tito_stt_latency_seconds = Histogram(
      "tito_stt_latency_seconds",
      "Speech-to-Text processing latency",
      buckets=[0.1, 0.25, 0.5, 1.0, 2.0, 5.0]
  )
  tito_llm_ttfb_seconds = Histogram(
      "tito_llm_ttfb_seconds",
      "LLM Time To First Byte (token)",
      buckets=[0.1, 0.25, 0.5, 1.0, 2.0, 5.0]
  )
  tito_llm_total_seconds = Histogram(
      "tito_llm_total_seconds",
      "LLM total response generation time",
      buckets=[0.5, 1.0, 2.0, 5.0, 10.0, 30.0]
  )
  tito_tts_latency_seconds = Histogram(
      "tito_tts_latency_seconds",
      "Text-to-Speech processing latency",
      buckets=[0.1, 0.25, 0.5, 1.0, 2.0, 5.0]
  )
  tito_e2e_turn_latency_seconds = Histogram(
      "tito_e2e_turn_latency_seconds",
      "End-to-end: user stops speaking -> agent starts speaking",
      buckets=[0.5, 1.0, 1.5, 2.0, 3.0, 5.0, 10.0]
  )
  ```
  - Instrumentar con `time.monotonic()` antes/despues de cada procesador en el pipeline
  - Labels: `provider` (openai/anthropic), `model` (gpt-4o/claude), `agent_id`
- [ ] **Metricas de uso por tenant**
  ```python
  tito_sessions_total = Counter(
      "tito_sessions_total",
      "Total sessions created",
      labelnames=["tenant_id", "agent_id", "provider"]
  )
  tito_session_minutes_total = Counter(
      "tito_session_minutes_total",
      "Total minutes consumed",
      labelnames=["tenant_id"]
  )
  ```
- [ ] **Metricas de transporte**
  ```python
  tito_transport_setup_seconds = Histogram(
      "tito_transport_setup_seconds",
      "Time to create room + generate tokens",
      labelnames=["provider"]
  )
  tito_transport_errors_total = Counter(
      "tito_transport_errors_total",
      "Transport setup failures",
      labelnames=["provider", "error_type"]
  )
  ```

### 7.2 Distributed Tracing (OpenTelemetry)

- [ ] Agregar dependencia `opentelemetry-api`, `opentelemetry-sdk`, `opentelemetry-instrumentation-fastapi`
- [ ] Configurar `TracerProvider` en `app/main.py` lifespan
- [ ] Exportar traces: `opentelemetry-exporter-otlp` → Jaeger / Tempo / Datadog
- [ ] Variables de entorno: `OTEL_EXPORTER_OTLP_ENDPOINT`, `OTEL_SERVICE_NAME=tito-runner`
- [ ] Spans automaticos en cada request HTTP (middleware de OpenTelemetry para FastAPI)
- [ ] Spans manuales por etapa del pipeline:
  ```
  trace: create_session
  ├── span: transport_setup (Daily/LiveKit room creation)
  ├── span: pipeline_run
  │   ├── span: stt_process (por cada turno del usuario)
  │   ├── span: llm_generate (por cada turno del agente)
  │   ├── span: tts_synthesize (por cada turno del agente)
  │   └── span: tool_call (si el LLM usa function calling)
  └── span: session_cleanup
  ```
- [ ] Propagar `trace_id` en los webhooks para correlacionar con el backend Laravel
- [ ] Propagar `trace_id` en los eventos de WebSocket

### 7.3 Health Checks Granulares

Actualmente `/health` solo reporta sesiones activas. No verifica dependencias.

- [ ] `GET /health/live` — Liveness probe (el proceso esta vivo?)
  - Siempre devuelve 200 si el servidor responde
  - Para Kubernetes `livenessProbe`
- [ ] `GET /health/ready` — Readiness probe (puede recibir trafico?)
  - Verifica conectividad con Redis (`PING`)
  - Verifica que no esta al maximo de capacidad
  - Verifica que el proveedor de transporte es alcanzable (Daily/LiveKit API health)
  - Para Kubernetes `readinessProbe`
  - Respuesta:
    ```json
    {
      "status": "ready",
      "checks": {
        "redis": { "status": "up", "latency_ms": 2 },
        "daily_api": { "status": "up", "latency_ms": 45 },
        "livekit_api": { "status": "up", "latency_ms": 30 },
        "capacity": { "status": "available", "active": 3, "max": 10 }
      }
    }
    ```
  - Si alguna dependencia falla → HTTP 503

### 7.4 Structured Logging

- [ ] Migrar de `logging` stdlib a **Loguru** consistentemente (ya esta en dependencias pero no se usa en todos los modulos)
- [ ] O alternativamente: configurar `logging` con `python-json-logger` para salida JSON
- [ ] Formato JSON estructurado para todos los logs:
  ```json
  {
    "timestamp": "2026-04-06T22:15:00Z",
    "level": "INFO",
    "event": "session_created",
    "session_id": "sess_a1b2c3d4",
    "agent_id": "luna-mvp",
    "tenant_id": "alloy-finance",
    "provider": "daily",
    "trace_id": "abc123...",
    "host_id": "runner-x7k2"
  }
  ```
- [ ] Campos consistentes en TODOS los logs: `session_id`, `agent_id`, `tenant_id`, `event`
- [ ] Contexto automatico: usar `contextvars` para propagar session_id sin pasarlo manualmente
- [ ] Integracion con stack de logs:
  - ELK (Elasticsearch + Logstash + Kibana)
  - Grafana Loki + Promtail
  - O CloudWatch Logs si es AWS

### 7.5 Alertas

- [ ] Reglas de Prometheus / Grafana:
  - `tito_active_sessions / MAX_CONCURRENT_SESSIONS > 0.8` → Runner casi lleno
  - `rate(tito_session_errors_total[5m]) / rate(tito_sessions_total[5m]) > 0.05` → Tasa de error > 5%
  - `histogram_quantile(0.95, tito_llm_ttfb_seconds) > 3` → LLM demasiado lento (p95 > 3s)
  - `histogram_quantile(0.95, tito_e2e_turn_latency_seconds) > 5` → Latencia end-to-end alta
  - `up{job="tito-runner"} == 0` → Runner caido
  - `tito_active_sessions == 0 AND rate(tito_sessions_total[1h]) == 0` → Runner sin trafico (posible falla silenciosa)
- [ ] Canales de alerta: Slack, PagerDuty, email
- [ ] Dashboard Grafana con:
  - Sesiones activas en tiempo real (por runner y total)
  - Latencias p50/p95/p99 por componente (STT, LLM, TTS, E2E)
  - Tasa de errores por tipo
  - Uso por tenant (sesiones, minutos)
  - Heatmap de duracion de sesiones

---

## 8. Base de Datos Persistente

Actualmente todo vive en Redis (volatil). Para produccion se necesita persistencia.

- [ ] PostgreSQL para datos permanentes:
  - Sesiones historicas (no solo activas)
  - Transcripciones completas
  - Grabaciones (metadata, link a S3)
  - Configuraciones de agentes (cache, no como fuente de verdad — eso es Laravel)
  - SIP trunks provisionados
  - Metricas de uso por tenant
- [ ] Migraciones con Alembic
- [ ] Redis sigue como cache de sesiones activas + pub/sub (no se reemplaza)

---

## 9. Auto-Scaling y Orquestacion

- [ ] **Metricas para HPA (Horizontal Pod Autoscaler)**
  - Custom metric: `tito_active_sessions / MAX_CONCURRENT_SESSIONS` (ratio de ocupacion)
  - Scale up cuando ratio > 0.7, scale down cuando < 0.3
- [ ] **Session affinity**
  - Una sesion debe permanecer en el mismo pod durante toda su vida
  - Sticky sessions en el ingress o usar el session_id para routing
- [ ] **Draining graceful**
  - Cuando un pod va a morir, dejar de aceptar nuevas sesiones
  - Esperar a que las sesiones activas terminen (o timeout)
  - `/health` devuelve `at_capacity: true` para que el LB no envie mas trafico
- [ ] **Queue de sesiones**
  - Si todos los runners estan llenos, encolar la solicitud en Redis/RabbitMQ
  - Cuando un runner se libera, toma la siguiente sesion de la cola
  - Timeout configurable de espera en cola

---

## 10. Canales Futuros

- [ ] **WhatsApp** — via Twilio/Meta API, audio messages como input
- [ ] **Telegram** — voice messages + voice calls
- [ ] **WebRTC directo** — sin Daily/LiveKit, P2P para reducir costos
- [ ] **Phone number provisioning** — comprar numeros telefonicos por tenant (Twilio)

---

## 11. Testing

- [ ] Tests unitarios de `SessionManager`, `TaskManager`
- [ ] Tests de integracion: crear sesion -> verificar que se crea room -> cleanup
- [ ] Tests de los schemas Pydantic (validacion de AgentConfig con distintos payloads)
- [ ] Tests de los endpoints con `httpx.AsyncClient` (TestClient de FastAPI)
- [ ] Load testing con Locust o k6 (cuantas sesiones concurrentes aguanta un runner)
- [ ] Tests de WebSocket (conexion, recepcion de eventos, desconexion)

---

## 12. CI/CD y DevOps

- [ ] GitHub Actions / GitLab CI pipeline:
  - Lint (ruff/flake8)
  - Type check (mypy)
  - Tests
  - Build Docker image
  - Push a container registry
- [ ] Helm chart para despliegue en Kubernetes
- [ ] Terraform/Pulumi para infraestructura (Redis, PostgreSQL, DNS, certificados)
- [ ] Secrets management (Vault, AWS Secrets Manager — no mas .env con keys reales)

---

## 13. Billing y Uso por Tenant

- [ ] **Tracking de minutos consumidos**
  - Cada sesion registra `duration` al terminar — acumular por tenant/mes
  - Tabla `usage_records`: tenant_id, agent_id, session_id, duration_seconds, created_at
  - Endpoint `GET /api/v1/tenants/{tenant_id}/usage?month=2026-04` para consultar consumo
- [ ] **Limites por plan**
  - Cada tenant tiene un plan con limites: max minutos/mes, max sesiones concurrentes, max agentes
  - Validar limites antes de crear sesion (rechazar con 402/429 si se excede)
  - Webhook `usage.limit_reached` al backend cuando un tenant llega al 80% y 100%
- [ ] **Reportes**
  - Desglose por agente: minutos, sesiones, errores, duracion promedio
  - Exportar CSV/JSON para facturacion

---

## 14. Reconexion y Resiliencia de Sesiones

- [ ] **Reconexion del usuario**
  - Si el usuario pierde conexion WebRTC y vuelve en < 60s, reconectar a la misma sesion
  - El pipeline sigue corriendo; el transporte detecta reconexion
  - Endpoint `POST /api/v1/sessions/{session_id}/rejoin` que devuelve nuevo token para la misma sala
- [ ] **Reconexion del runner**
  - Si el runner muere, otro runner puede retomar la sesion desde Redis (session metadata)
  - Requiere que la sala WebRTC siga activa en Daily/LiveKit
- [ ] **Webhook retry**
  - Actualmente los webhooks son fire-and-forget con timeout de 5s y sin retry
  - Implementar retry con backoff exponencial (3 intentos: 1s, 5s, 30s)
  - Dead letter queue para webhooks que fallan 3 veces

---

## 15. Conversation Flows (pipecat-ai-flows)

La dependencia `pipecat-ai-flows` ya esta en `pyproject.toml` pero no se usa.

- [ ] Soporte para `architecture.type: "node-graph"` (ya existe en el schema pero solo se usa `pipeline`)
  - Flujos conversacionales con nodos: saludo -> recopilar datos -> confirmar -> despedida
  - Transiciones condicionales entre nodos
- [ ] Editor visual de flows en el frontend admin
- [ ] Nodos especiales: transfer_to_human, collect_input, confirm_action, end_call

---

## 16. Contexto Externo e Inyeccion de Datos

- [ ] **Inyectar datos de CRM / backend en el prompt**
  - `orchestration.session_context` ya existe en el schema pero no se usa en el pipeline
  - Ejemplo: pasar nombre del cliente, historial de compras, ticket abierto
  - El runner hace un GET al backend para obtener contexto antes de arrancar el LLM
- [ ] **Memoria entre sesiones**
  - Recordar conversaciones previas del mismo usuario
  - Almacenar resumen de cada sesion y adjuntarlo como contexto en la siguiente
  - Requiere identificar al usuario (por telefono, email, o ID)

---

## Deuda tecnica pendiente

- [ ] Corregir import roto: `pipecat.processors.audio.audio_player_processor` en `pipeline_builder.py`
- [ ] Endpoint `GET /api/v1/sessions` actualmente devuelve lista vacia (placeholder) — implementar listado real desde Redis
- [ ] Rotar API keys expuestas en `.env` (LiveKit, Daily, OpenAI, Cartesia, Deepgram, Google)
- [ ] Tests unitarios para los nuevos endpoints
- [ ] El `AgentConfig` se pasa completo en cada request — evaluar si el runner deberia cachear configs y recibir solo `agent_id`
