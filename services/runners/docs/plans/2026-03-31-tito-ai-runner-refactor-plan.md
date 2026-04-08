# Plan de Refactorización: app.tito.ai a Arquitectura "Runner" Escalable

Este documento describe el plan paso a paso para transformar `app.tito.ai` adoptando los patrones de alta disponibilidad y control de concurrencia utilizados en `bin-pipecat-manager`. El objetivo es convertir la aplicación en un microservicio de tipo "Worker/Runner" verdaderamente escalable, eliminando cuellos de botella y asegurando el control absoluto sobre el ciclo de vida de los agentes de voz.

> **Referencia de arquitectura:** Los patrones descritos aquí están basados directamente en `bin-pipecat-manager/pkg/pipecatcallhandler/` del monorepo de VoIPBin. Cada fase tiene su equivalente en Go documentado.

---

## Fase 0: Control de Backpressure y Buffer Pool

Antes de cualquier refactorización de lógica, hay que resolver el problema de memoria y presión sobre el GC. En `bin-pipecat-manager`, cada sesión tiene un channel de tamaño fijo para los frames de audio saliente. Si el consumer (el transporte hacia el cliente) no puede seguir el ritmo del producer (el pipeline de Pipecat), los frames se descartan en lugar de bloquear el loop principal.

### 0.1 Channel de frames con tamaño fijo (backpressure por descarte)

En `app/services/session_manager.py`, al crear una sesión, inicializar una `asyncio.Queue` con `maxsize` fijo:

```python
RUNNER_FRAME_QUEUE_MAXSIZE = 150  # ~3 segundos a 50fps
session["frame_queue"] = asyncio.Queue(maxsize=RUNNER_FRAME_QUEUE_MAXSIZE)
```

En el sender de audio, usar `put_nowait` en lugar de `await put()`. Si la queue está llena, descartar el frame y registrar el evento:

```python
try:
    session["frame_queue"].put_nowait(audio_frame)
except asyncio.QueueFull:
    session["dropped_frames"] += 1
    if session["dropped_frames"] % 100 == 0:
        logger.warning(f"Dropped {session['dropped_frames']} frames. session_id={session_id}")
```

Al cerrar la sesión, loguear el total de frames descartados para detectar problemas de rendimiento en producción.

**Equivalente en Go:** `defaultRunnerWebsocketChanBufferSize = 150` en `main.go` y el campo `DroppedFrames atomic.Int64` en `pipecatcall.Session`.

### 0.2 Timeout en el push de frames

El sender nunca debe bloquearse indefinidamente esperando escribir un frame. Usar `asyncio.wait_for` con un timeout corto (50ms):

```python
PUSH_FRAME_TIMEOUT = 0.050  # 50ms

try:
    await asyncio.wait_for(websocket.send_bytes(frame_data), timeout=PUSH_FRAME_TIMEOUT)
except asyncio.TimeoutError:
    logger.warning(f"Frame push timeout. session_id={session_id}")
```

**Equivalente en Go:** `defaultPushFrameTimeout = 50 * time.Millisecond` en `main.go`.

### 0.3 Buffer pool para procesamiento de audio

Si el runner hace resampling o transformaciones de audio en Python, usar un pool de buffers `bytearray` pre-allocados para evitar presión sobre el GC de CPython en el hot path:

```python
import queue

_buffer_pool: queue.SimpleQueue[bytearray] = queue.SimpleQueue()
BUFFER_SIZE = 4096  # ajustar según frame size

def get_buffer() -> bytearray:
    try:
        return _buffer_pool.get_nowait()
    except queue.Empty:
        return bytearray(BUFFER_SIZE)

def put_buffer(buf: bytearray) -> None:
    buf[:] = b'\x00' * len(buf)  # reset
    _buffer_pool.put(buf)
```

**Equivalente en Go:** `bufferpool.go` con `sync.Pool` en `bin-pipecat-manager`.

---

## Fase 1: Implementación del Task Manager Determinista

Actualmente, Tito delega la ejecución del agente a `BackgroundTasks` de FastAPI o a Celery, perdiendo el control del puntero al proceso asíncrono. Debemos implementar un gestor que retenga las referencias a las tareas (coroutines) de `asyncio`.

### 1.1 Crear `app/services/task_manager.py`
Crear un módulo dedicado a gestionar el ciclo de vida de las tareas asíncronas de Pipecat, usando un diccionario en memoria para mapear `session_id` a objetos `asyncio.Task`.

**Acciones:**
*   Implementar una clase `TaskManager` con métodos `add(session_id: str, task: asyncio.Task)` y `remove(session_id: str)`.
*   Implementar un método `stop(session_id: str)` que invoque `task.cancel()` y espere la limpieza controlada.

### 1.2 Refactorizar `app.api.v1.sessions.create_session`
*   Eliminar la dependencia de `BackgroundTasks` (y de Celery en el `worker.py` si se usaba para la ejecución del bot en tiempo real).
*   Dentro del endpoint, tras generar la configuración y las credenciales (Daily/LiveKit), crear la tarea con `asyncio.create_task(...)` e inmediatamente registrarla en el `task_manager`.

### 1.3 Startup paralelo: transporte + runner simultáneos

Este es el patrón más crítico del monorepo. **No** se debe esperar a que el transporte (Daily/LiveKit) esté listo antes de iniciar el pipeline de Pipecat. Ambos deben arrancar en paralelo:

```python
async def create_session(session_id: str, config: SessionConfig):
    # Crear sesión con transporte pendiente
    session = await session_manager.create(session_id, config)

    transport_error: asyncio.Future = asyncio.get_event_loop().create_future()

    async def connect_transport():
        try:
            token = await transport_provider.get_token(session_id)
            await session.set_transport_ready(token)
            transport_error.set_result(None)
        except Exception as e:
            transport_error.set_result(e)

    # Lanzar transporte y runner en paralelo
    asyncio.create_task(connect_transport())
    runner_task = asyncio.create_task(run_pipeline(session))
    task_manager.add(session_id, runner_task)

    # Esperar solo la fase de conexión del transporte
    err = await transport_error
    if err is not None:
        session.cancel()
        task_manager.remove(session_id)
        raise HTTPException(status_code=503, detail=f"Transport setup failed: {err}")

    return {"session_id": session_id, "status": "started"}
```

**Equivalente en Go:** El patrón `astErrCh := make(chan error, 1)` en `start.go` donde Asterisk WS y el Python runner arrancan en goroutines paralelas.

### 1.4 Crear Endpoint Explícito `/sessions/{session_id}/stop`
*   Modificar el método `DELETE` actual (que solo elimina la sala). Debe primero llamar a `task_manager.stop(session_id)` para asegurar que el agente Pipecat termine su pipeline, limpie sus recursos locales (modelos, memoria) y luego elimine la sala del proveedor.

## Fase 2: Robustez en Desconexiones y Limpieza de Recursos (Clean Shutdown)

Los agentes Pipecat consumen recursos significativos (memoria para LLMs, conexiones a STT/TTS). Si un usuario cierra la pestaña, el agente debe morir en milisegundos.

### 2.1 Transport-Ready Channel (señalización de disponibilidad)

Antes de que el sender de audio intente escribir frames, debe esperar a que el transporte esté listo. Usar un `asyncio.Event` como señal:

```python
class Session:
    def __init__(self):
        self._transport_ready = asyncio.Event()
        self.transport = None

    async def set_transport_ready(self, token: str):
        self.transport = await connect_transport(token)
        self._transport_ready.set()

    async def wait_transport_ready(self):
        await self._transport_ready.wait()
```

El sender de audio espera este evento antes de empezar a escribir:

```python
async def audio_sender(session: Session):
    await session.wait_transport_ready()
    # ahora es seguro enviar audio
    async for frame in session.frame_queue:
        await session.transport.send(frame)
```

**Equivalente en Go:** El channel `ConnAstReady chan struct{}` en `pipecatcall.Session` que se cierra con `close(connAstReady)` cuando la conexión Asterisk está lista.

### 2.2 Lifecycle Monitor (goroutine/task de vigilancia)

Necesitas una task dedicada que observe dos señales simultáneamente: el contexto de la sesión y el cierre del transporte. Cuando cualquiera de las dos ocurra, dispara el `terminate()`:

```python
async def lifecycle_monitor(session: Session):
    transport_done = asyncio.create_task(session.transport.wait_disconnected())
    ctx_done = asyncio.create_task(session.ctx.wait())

    done, pending = await asyncio.wait(
        [transport_done, ctx_done],
        return_when=asyncio.FIRST_COMPLETED
    )

    for task in pending:
        task.cancel()

    logger.info(f"Lifecycle monitor triggered terminate. session_id={session.id}")
    await terminate(session)
```

Lanzar esta task en paralelo al arrancar la sesión:

```python
asyncio.create_task(lifecycle_monitor(session))
```

**Equivalente en Go:**
```go
go func() {
    select {
    case <-se.Ctx.Done():
    case <-se.ConnAstDone:
    }
    h.terminate(context.Background(), pc)
}()
```

### 2.3 Enlazar Transporte con Cancelación
En `AgentPipelineEngine.run()`:
*   Añadir *event handlers* al transporte (`self.transport`) para los eventos de desconexión (`on_disconnected` o `on_participant_left` si es el único participante).
*   En este handler, invocar explícitamente la cancelación de la tarea principal de Pipecat (`self.task.cancel()`) para abortar el ciclo de eventos del pipeline inmediatamente, en lugar de esperar a que salte el `InactivityTimeout`.

### 2.4 Bloque `finally` de Limpieza Segura
*   Envolver la ejecución del runner (`await self.runner.run(self.task)`) en un bloque `try...except asyncio.CancelledError...finally`.
*   En el bloque `finally`, asegurar el cierre explícito de:
    *   Transporte (`await self.transport.cleanup()`).
    *   Servicios TTS/STT/LLM.
    *   Eliminación del registro en el `task_manager`.
    *   Log del total de `dropped_frames` para diagnóstico en producción.

## Fase 3: Desacoplamiento del Event Streaming (Webhooks Asíncronos)

Hacer llamadas HTTP sincrónicas (bloqueantes) a un backend de Laravel mediante `WebhookService` directamente desde el hilo principal del agente es peligroso. Un timeout o latencia en Laravel puede trabar el pipeline de audio.

### 3.1 Publicación de eventos en goroutines/tasks separadas

El patrón del monorepo no usa Redis Pub/Sub para esto. Usa RabbitMQ (`notifyHandler.PublishEvent`) pero lo despacha en goroutines separadas para que la latencia de red no bloquee el loop de audio:

```go
// En Go (bin-pipecat-manager):
go h.notifyHandler.PublishEvent(se.Ctx, message.EventTypeBotTranscription, msg)
```

El equivalente en Python es lanzar la publicación como una task de asyncio:

```python
# En Python (Tito):
asyncio.create_task(
    webhook_service.publish(EventType.BOT_TRANSCRIPTION, payload)
)
```

Esto garantiza que el runner vuelva al procesamiento de audio en menos de 1ms, independientemente de la latencia del backend.

### 3.2 Elección del bus de eventos: RabbitMQ vs Redis Pub/Sub

Si el stack de VoIPBin ya tiene RabbitMQ disponible (lo tiene), es más consistente usarlo que añadir Redis como canal de eventos. Redis Pub/Sub es válido si RabbitMQ no está disponible o si se quiere un canal de baja latencia para métricas en tiempo real. La decisión debe ser explícita:

*   **Opción A (recomendada para consistencia con el monorepo):** Publicar eventos en RabbitMQ usando `aio-pika`. El "Control Plane" (Laravel o un worker dedicado) consume la cola.
*   **Opción B (válida para métricas en tiempo real):** Redis Pub/Sub para eventos de alta frecuencia (transcripciones parciales, métricas de audio). RabbitMQ para eventos de negocio (session.started, session.ended).

### 3.3 Eventos a publicar

Basado en los tipos de eventos de `bin-pipecat-manager/models/message/`:

| Evento | Descripción |
|--------|-------------|
| `session.started` | El pipeline arrancó correctamente |
| `session.ended` | El pipeline terminó (con causa: user_disconnect, stop_requested, error) |
| `transcript.user` | Transcripción final del usuario |
| `transcript.bot` | Transcripción del bot |
| `llm.user` | Texto enviado al LLM |
| `llm.bot` | Respuesta del LLM (acumulada hasta `BotLLMStopped`) |

**Nota sobre acumulación de texto LLM:** El bot emite texto en streaming (múltiples eventos `BotLLMText`). No publicar cada fragmento. Acumular en `session["llm_bot_text"]` y publicar el texto completo solo cuando llegue el evento `BotLLMStopped`. Ver el patrón en `runner.go`:

```go
case pipecatframe.RTVIFrameTypeBotLLMText:
    se.LLMBotText += msg.Data.Text  // acumular

case pipecatframe.RTVIFrameTypeBotLLMStopped:
    botText := se.LLMBotText
    se.LLMBotText = ""
    go h.notifyHandler.PublishEvent(se.Ctx, message.EventTypeBotLLM, ...)  // publicar completo
```

## Fase 4: Desacoplamiento de Estado (Control Plane vs Data Plane)

Tito almacena configuraciones en memoria (`session_manager.save_session`). Para ser un microservicio escalable (horizontalmente en múltiples pods/contenedores), el estado debe ser externo.

### 4.1 Separación clara: estado de configuración vs estado de runtime

No todo el estado puede ir a Redis. Hay dos categorías:

**Estado de configuración (va a Redis):** Datos que cualquier instancia necesita para saber si una sesión existe y cómo está configurada.
```python
# En Redis (TTL = duración máxima de sesión, ej: 2 horas)
{
    "session_id": "...",
    "customer_id": "...",
    "llm_type": "openai",
    "tts_type": "elevenlabs",
    "status": "running",
    "host_id": "pod-abc123",  # qué instancia posee esta sesión
    "created_at": "..."
}
```

**Estado de runtime (queda en memoria local de la instancia):** Datos efímeros que solo tienen sentido en la instancia que ejecuta el pipeline.
```python
# En memoria local (task_manager)
{
    "task": asyncio.Task,           # referencia a la coroutine
    "frame_queue": asyncio.Queue,   # buffer de frames de audio
    "llm_bot_text": str,            # acumulador de texto LLM en streaming
    "dropped_frames": int,          # contador de frames descartados
    "transport_ready": asyncio.Event
}
```

**Equivalente en Go:** `mapPipecatcallSession map[uuid.UUID]*pipecatcall.Session` en memoria local + DB/Redis para el estado persistente.

### 4.2 Migrar Estado de Sesiones a Redis
*   Refactorizar `app/services/session_manager.py` para que lea y escriba el estado de configuración en Redis.
*   Incluir el campo `host_id` (nombre del pod/instancia) para que el balanceador de carga pueda enrutar requests de `/stop` a la instancia correcta.
*   El estado efímero (la instancia del objeto `asyncio.Task`) se queda en el `task_manager` de la instancia específica que ejecuta la llamada.

### 4.3 Protocolo de comunicación WebSocket con el runner: Protobuf

Si Tito va a integrarse con `bin-pipecat-manager` como runner Python, la comunicación WebSocket debe usar **Protobuf frames binarios**, no JSON. El monorepo define los frames en `bin-pipecat-manager/proto/` y los usa en `pipecatframe.go`.

Los tipos de frames relevantes son:
*   `Frame_Audio`: audio PCM 16-bit, mono, con `sample_rate` y `num_channels`
*   `Frame_Text`: mensajes de control (ej: `FLUSH_MEDIA`)
*   `Frame_Transcription`: transcripciones del usuario
*   `Frame_Message`: mensajes RTVI (JSON embebido en el frame binario)

Si Tito opera de forma standalone (sin `bin-pipecat-manager` como proxy), puede mantener su propio protocolo, pero debe documentar explícitamente la decisión.

---

## Fase 5: Observabilidad y Métricas de Producción

Esta fase no estaba en el plan original pero es necesaria para operar el microservicio en producción.

### 5.1 Métricas por sesión al cierre

Al terminar cada sesión, loguear un resumen estructurado:

```python
logger.info("session_ended", extra={
    "session_id": session_id,
    "duration_seconds": (time.time() - session["created_at"]),
    "dropped_frames": session["dropped_frames"],
    "termination_reason": reason,  # "user_disconnect" | "stop_requested" | "error" | "timeout"
})
```

### 5.2 Métricas de concurrencia

Exponer un endpoint `/metrics` (compatible con Prometheus) con:
*   `tito_active_sessions`: gauge con el número de sesiones activas
*   `tito_dropped_frames_total`: counter acumulado de frames descartados
*   `tito_session_duration_seconds`: histogram de duración de sesiones

**Equivalente en Go:** Los dashboards de Grafana en `monitoring/grafana/dashboards/pipecat-manager.json`.

### 5.3 Health check con estado real

El endpoint `/health` debe reflejar el estado real del runner, no solo "estoy vivo":

```python
@app.get("/health")
async def health():
    active = task_manager.count()
    return {
        "status": "ok",
        "active_sessions": active,
        "max_sessions": MAX_CONCURRENT_SESSIONS,
        "at_capacity": active >= MAX_CONCURRENT_SESSIONS
    }
```

Esto permite que Kubernetes deje de enviar tráfico a una instancia que está al límite de capacidad.

---

## Resumen de la Nueva Arquitectura

1.  **Solicitud:** Laravel (o Frontend) pide iniciar una llamada a `Tito API`.
2.  **Preparación paralela:** `Tito API` lanza simultáneamente la conexión al transporte (Daily/LiveKit) y el pipeline de Pipecat. Ambos corren en paralelo. Si el transporte falla, se cancela el contexto y se limpia la sesión.
3.  **Transport-Ready signal:** El sender de audio espera el `asyncio.Event` de transporte listo antes de intentar escribir frames.
4.  **Ejecución:** La tarea se registra en el `TaskManager` local. El estado de configuración se persiste en Redis con el `host_id` de la instancia.
5.  **Streaming de eventos:** Mientras habla, el agente publica eventos al bus (RabbitMQ/Redis) en tasks separadas, sin bloquear el loop de audio.
6.  **Backpressure:** El channel de frames tiene tamaño fijo (150 frames ≈ 3s). Si se llena, los frames se descartan y se contabilizan.
7.  **Lifecycle monitor:** Una task dedicada observa `ctx.done()` y `transport.disconnected`. Cuando cualquiera dispara, ejecuta `terminate()`.
8.  **Finalización:** Si el usuario cuelga, el evento de desconexión del transporte mata el `Task` vía el lifecycle monitor. Si Laravel lo pide, llama al endpoint `/stop`. En ambos casos: se limpian recursos, se publican eventos finales, se loguean métricas de sesión y la memoria queda libre.

### Diagrama de estados de una sesión

```
CREATE_SESSION
     │
     ├──[paralelo]──► connect_transport() ──► transport_ready.set()
     │                                              │
     └──[paralelo]──► run_pipeline()  ◄─────────────┘
                           │
                    [lifecycle_monitor]
                           │
              ┌────────────┴────────────┐
         ctx.done()            transport.disconnected()
              │                         │
              └────────────┬────────────┘
                           │
                      terminate()
                           │
                    cleanup_resources()
                    publish(session.ended)
                    log_metrics()
                    task_manager.remove()
```

### Tabla de equivalencias Go → Python

| Concepto | Go (bin-pipecat-manager) | Python (Tito) |
|----------|--------------------------|---------------|
| Sesión en memoria | `map[uuid.UUID]*Session` + `sync.Mutex` | `dict[str, Session]` + `asyncio.Lock` |
| Cancelación de contexto | `context.WithCancel` | `asyncio.Event` o `asyncio.Task.cancel()` |
| Transport-ready signal | `ConnAstReady chan struct{}` | `asyncio.Event` |
| Transport-done signal | `ConnAstDone chan struct{}` | `asyncio.Event` o callback |
| Frame buffer | `chan *SessionFrame` (size 150) | `asyncio.Queue(maxsize=150)` |
| Dropped frames | `atomic.Int64` | `int` en session dict |
| Frame push timeout | `50ms select + default` | `asyncio.wait_for(..., timeout=0.05)` |
| Buffer pool | `sync.Pool[*bytes.Buffer]` | `queue.SimpleQueue[bytearray]` |
| Event publishing | `go notifyHandler.PublishEvent(...)` | `asyncio.create_task(publish(...))` |
| Lifecycle monitor | `goroutine select{ctx, connDone}` | `asyncio.wait([ctx_task, transport_task], FIRST_COMPLETED)` |
| Startup paralelo | `go func() + chan error` | `asyncio.create_task() + asyncio.Future` |
