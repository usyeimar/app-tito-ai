# Plan de Refactorización v2: app.tito.ai → Arquitectura Runner Escalable (DONE)

> **Versión:** 2.0 — 2026-04-06
> **Basado en:** `2026-03-31-tito-ai-runner-refactor-plan.md`
> **Estado:** COMPLETADO ✅

---

## Auditoría del Estado Actual (DONE)

| Archivo | Líneas | Estado relevante |
|---|---|---|
| `app/api/v1/sessions.py` | DONE | Refactorizado: TaskManager + Response inmediata |
| `app/services/session_manager.py` | DONE | Migrado a Redis + Pub/Sub |
| `app/services/agents/pipelines/agent_pipeline_engine.py` | DONE | Refactorizado en múltiples archivos, Cleanup determinista |
| `app/services/webhook_service.py` | DONE | `emit_event_async` (fire-and-forget) |
| `app/worker.py` | ELIMINADO | Deuda técnica eliminada |
| `app/main.py` | DONE | Lifespan con `task_manager.stop_all()` |

---

## Fase 0: Deuda Técnica y Backpressure (DONE)

### 0.0 Eliminar Celery (DONE)
### 0.1 Channel de frames con tamaño fijo (DONE)
### 0.2 Timeout en el push de frames (DONE)
### 0.3 Graceful Shutdown al reiniciar la app (DONE)

---

## Fase 1: Task Manager Determinista (DONE)

### 1.1 Crear `app/services/task_manager.py` (DONE)
### 1.2 Refactorizar `sessions.py`: Startup paralelo (DONE)
### 1.3 Endpoint `DELETE /sessions/{session_id}` (DONE)

---

## Fase 2: Robustez en Desconexiones y Limpieza de Recursos (DONE)

### 2.1 Transport-Ready Signal (DONE)
### 2.2 Lifecycle Monitor (DONE)
### 2.3 Refactorizar `agent_pipeline_engine.py` (DONE)
### 2.4 Bloque `finally` de Limpieza Segura (DONE)

---

## Fase 3: Desacoplamiento del Event Streaming (DONE)

### 3.0 Corregir WebhookService (DONE)
### 3.1 Publicación de eventos en tasks separadas (DONE)
### 3.2 Bus de eventos (Redis Pub/Sub) (DONE)
### 3.3 Acumulación de texto LLM (DONE)

---

## Fase 4: Desacoplamiento de Estado + WebSocket Multi-Instancia (DONE)

### 4.1 Separación de estado (DONE)
### 4.2 Migrar `session_manager.py` a Redis (DONE)
### 4.3 WebSocket con Redis Pub/Sub (DONE)
### 4.4 Routing de `/stop` vía Redis Control (DONE)

---

## Fase 5: Observabilidad y Métricas de Producción (DONE)

### 5.1 Métricas por sesión al cierre (DONE)
### 5.2 Endpoint `/metrics` (Prometheus) (DONE)
### 5.3 Health check con estado real (DONE)
### 5.4 Rate limiting en `POST /sessions/` (DONE)
