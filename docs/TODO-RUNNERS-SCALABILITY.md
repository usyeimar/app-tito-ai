# TODO: Runners Scalability Improvements

## Contexto

El servicio de runners (FastAPI/Pipecat) usa Redis para todo su estado: sesiones, trunks, deployments, pub/sub.
Problemas actuales:

- Datos durables (trunks, deployments) se guardan sin TTL y se pierden si Redis se reinicia
- `list_sessions` usa `KEYS *` que bloquea Redis en O(n)
- No hay soporte para multiples instancias de runner (single endpoint)
- `broadcast()` no esta implementado (es `pass`)
- Servicios acceden a `session_manager._redis` directamente (acoplamiento)

Laravel ya tiene un patron probado con `AgentRedisSyncService` que sincroniza configs de agentes a Redis via jobs.
Extenderemos ese patron para trunks y deployments.

---

## Fase 1 - Fix Redis inmediatos (Runner side only) - COMPLETADA ✅

### 1A. Reemplazar `KEYS *` con sets de indice ✅

- [x] **Archivo:** `services/runners/app/services/session_manager.py`
    - [x] `save_session()`: agregar `sadd("session:index:global", session_id)` y `sadd(f"session:index:host:{HOST_ID}", session_id)`
    - [x] `delete_session()`: agregar `srem` correspondientes
    - [x] `list_sessions()`: reemplazar `keys("session:*")` por `smembers("session:index:global")` con limpieza lazy de entries stale
    - [x] Agregar parametro opcional `host_id` para filtrar por instancia

### 1B. Desacoplar conexiones Redis ✅

- [x] **`services/runners/app/services/trunk_service.py`** - crear su propio `aioredis.from_url(settings.REDIS_URL)`
- [x] **`services/runners/app/services/deployment_service.py`** - idem
- [x] **`services/runners/app/services/agent_resolution_service.py`** - idem
- [x] Eliminar `from app.services.session_manager import session_manager` y el acceso a `session_manager._redis`

### 1C. Implementar `broadcast()` con pub/sub global ✅

- [x] **`services/runners/app/services/session_manager.py`**
    - [x] Canal: `session:broadcast:global`
    - [x] `start_global_listener()` en startup: subscribe y forward a todos los WS locales
    - [x] `broadcast()`: publish al canal global (reemplaza el `pass`)
    - [x] `stop_global_listener()` en shutdown
- [x] **`services/runners/app/main.py`**
    - [x] Llamar `start_global_listener()` en startup
    - [x] Llamar `stop_global_listener()` en shutdown

---

## Fase 2 - Trunk como modelo en Laravel (source of truth)

### 2A. Modelo y migracion ✅

- [x] Crear migracion `create_trunks_table` en `database/migrations/tenant/Agent/`
- [x] Crear modelo `app/Models/Tenant/Agent/Trunk.php`
- [x] Crear factory `database/factories/tenant/Agent/TrunkFactory.php`

**Schema:**

| Campo                | Tipo                               | Notas                                                        |
| -------------------- | ---------------------------------- | ------------------------------------------------------------ |
| id                   | ulid (PK)                          | ✅                                                           |
| agent_id             | foreignUlid (nullable)             | cascades null on delete ✅                                   |
| workspace_slug       | string (indexed)                   | ✅                                                           |
| name                 | string                             | ✅                                                           |
| mode                 | string (inbound/register/outbound) | ✅                                                           |
| max_concurrent_calls | integer (default 10)               | ✅                                                           |
| codecs               | jsonb (default ["ulaw","alaw"])    | ✅                                                           |
| status               | string (default "active")          | ✅                                                           |
| inbound_auth         | jsonb (nullable)                   | ✅ {auth_type, username, password}                           |
| routes               | jsonb (nullable)                   | ✅ [{pattern, agent_id, enabled}]                            |
| sip_host             | string (nullable)                  | ✅                                                           |
| sip_port             | integer (default 5060)             | ✅                                                           |
| register_config      | jsonb (nullable)                   | ✅ {server, port, username, password, register_interval}     |
| outbound             | jsonb (nullable)                   | ✅ {trunk_name, server, port, username, password, caller_id} |
| timestamps           |                                    | ✅                                                           |

- [x] Agregar relacion `trunks(): HasMany` en `app/Models/Tenant/Agent/Agent.php`

### 2B. TrunkRedisSyncService ✅

- [x] Crear `app/Services/Tenant/Agent/Runner/TrunkRedisSyncService.php`
    - Seguir patron exacto de `AgentRedisSyncService.php`
    - Keys: `trunk:{trunk_id}` con TTL 86400s
    - Index: `trunk:index:{workspace_slug}` (set)
    - Metodos: `sync()`, `remove()`, `isSynced()`, `touch()`, `getSyncedTrunkIds()`

### 2C. Job y comando artisan ✅

- [x] Crear `app/Jobs/Tenant/Agent/SyncTrunkToRedisJob.php` (queue: `trunk-sync`, tries: 3)
- [x] Crear `app/Console/Commands/Tenant/Agent/SyncTrunksToRedisCommand.php`
    - Signature: `tenant:trunks:sync-to-redis {--trunk=} {--workspace=} {--chunk=100} {--sync}`

### 2D. CRUD API en Laravel ✅

- [x] Crear `app/Http/Controllers/Tenant/API/Agent/TrunkController.php` (resource controller)
- [x] Crear `app/Http/Requests/Tenant/Agent/StoreTrunkRequest.php`
- [x] Crear `app/Http/Requests/Tenant/Agent/UpdateTrunkRequest.php`
- [x] Agregar rutas en `routes/tenant/api/ai/trunks.php`
- [x] Dispatch `SyncTrunkToRedisJob` en create/update/delete
- [x] Data Transfer Objects: CreateTrunkData, UpdateTrunkData
- [x] Actions: CreateTrunk, UpdateTrunk, DeleteTrunk, ListTrunks, ShowTrunk

### 2E. Runner TrunkService -> read-only ✅

- [x] Crear `services/runners/app/services/trunk_resolution_service.py`
    - Patron identico a `agent_resolution_service.py`
    - Redis first -> fallback a `GET {BACKEND_URL}/api/v1/ai/trunks/{trunk_id}`
- [x] Refactorizar `services/runners/app/services/trunk_service.py`
    - [x] Eliminar metodos de escritura (create, update, delete, add_route, remove_route, rotate_credentials)
    - [x] Mantener metodos de lectura y gestion de llamadas (calls son efimeras)
    - [x] Usar `TrunkResolutionService` para resolver trunks
- [x] Refactorizar `services/runners/app/api/v1/trunks.py`
    - [x] Eliminar endpoints POST/PATCH/DELETE para trunks
    - [x] Mantener GET y endpoints de calls

### 2F. Tests ✅

- [x] Feature test para TrunkController CRUD (`tests/Feature/Tenant/API/Agent/TrunkControllerTest.php`)
- [x] Feature test para TrunkRedisSyncService (`tests/Feature/Tenant/Agent/Runner/TrunkRedisSyncServiceTest.php`)
- [x] Unit test para SyncTrunkToRedisJob (`tests/Unit/Jobs/Tenant/Agent/SyncTrunkToRedisJobTest.php`)

---

## Fase 3 - Runner Registry + Load Balancing

### 3A. Heartbeat del runner ✅

- [x] **`services/runners/app/core/config.py`** - `RUNNER_ADVERTISE_URL` agregado
- [x] **`services/runners/app/services/runner_registry.py`** - RunnerRegistryService creado
- [x] **`services/runners/app/main.py`**
    - [x] Startup: registrar en Redis `runner:{HOST_ID}` con TTL 30s + `sadd runner:index`
    - [x] Background task: heartbeat cada 15s actualiza TTL y session count
    - [x] Shutdown: `srem runner:index` + `del runner:{HOST_ID}`

**Payload runner en Redis:**

```json
{
    "host_id": "runner-abc123",
    "url": "http://runner-abc123:8000",
    "active_sessions": 7,
    "max_sessions": 10,
    "sip_enabled": true,
    "last_heartbeat": 1713020400.0
}
```

### 3B. RunnerRegistry en Laravel ✅

- [x] Crear `app/Services/Tenant/Agent/Runner/RunnerRegistry.php`
    - `getAvailableRunner()`: lee `runner:index` de Redis, elige el runner con menor carga
    - `getRunner()`: obtiene runner específico
    - `getAllRunners()`: lista todos los runners
    - `removeRunner()`: elimina runner del registry
- [x] Modificar `app/Services/Tenant/Agent/Runner/RunnerClient.php`
    - Inyectar `RunnerRegistry`
    - `request()`: usar registry si `config('runners.use_registry')` es true
    - `terminateSession()`: buscar host_id de la sesion y enviar DELETE al runner correcto
- [x] Modificar `config/runners.php`
    - Agregar `'use_registry' => env('TITO_RUNNERS_USE_REGISTRY', false)`

---

## Verificacion

### Fase 1 - COMPLETADA ✅

- [x] Verificar que `list_sessions` no usa `KEYS *`
- [x] Crear/eliminar sesiones y verificar que los sets de indice se actualizan
- [x] Verificar que broadcast envia shutdown a WebSockets conectados

### Fase 2 - COMPLETA ✅

- [x] `vendor/bin/sail artisan migrate` corre sin errores
- [x] `vendor/bin/sail artisan test --compact --filter=Trunk` pasa
- [x] `vendor/bin/sail artisan tenant:trunks:sync-to-redis` sincroniza a Redis
- [x] Crear trunk via API Laravel, verificar que aparece en Redis
- [x] Runner puede leer trunk desde Redis y fallback a API
- [x] trunk_service.py y trunks.py refactorizados a modo read-only

### Fase 3 - COMPLETA ✅

- [x] Levantar 2 runners con HOST_ID distintos
- [x] Verificar que ambos aparecen en `runner:index` de Redis
- [x] Crear sesion desde Laravel, verificar que va al runner con menor carga
- [x] Terminar sesion, verificar que DELETE va al runner correcto

---

## Resumen de estado

| Fase                                      | Estado | Completado |
| ----------------------------------------- | ------ | ---------- |
| Fase 1 (KEYS, desacoplamiento, broadcast) | ✅     | 100%       |
| Fase 2A (Modelo y migracion)              | ✅     | 100%       |
| Fase 2B (TrunkRedisSyncService)           | ✅     | 100%       |
| Fase 2C (Job y comando)                   | ✅     | 100%       |
| Fase 2D (CRUD API)                        | ✅     | 100%       |
| Fase 2E (Runner read-only)                | ✅     | 100%       |
| Fase 2F (Tests)                           | ✅     | 100%       |
| Fase 3A (Heartbeat)                       | ✅     | 100%       |
| Fase 3B (RunnerRegistry)                  | ✅     | 100%       |
| Fase 3C (RunnerClient)                    | ✅     | 100%       |
| Fase 3D (Config)                          | ✅     | 100%       |

---

## Archivos creados/modificados

### Laravel ✅

| Archivo                                                          | Estado        |
| ---------------------------------------------------------------- | ------------- |
| `app/Models/Tenant/Agent/Trunk.php`                              | ✅ Creado     |
| `app/Services/Tenant/Agent/Runner/TrunkRedisSyncService.php`     | ✅ Creado     |
| `app/Jobs/Tenant/Agent/SyncTrunkToRedisJob.php`                  | ✅ Creado     |
| `app/Console/Commands/Tenant/Agent/SyncTrunksToRedisCommand.php` | ✅ Creado     |
| `app/Http/Controllers/Tenant/API/Agent/TrunkController.php`      | ✅ Creado     |
| `app/Actions/Tenant/Agent/CreateTrunk.php`                       | ✅ Creado     |
| `app/Actions/Tenant/Agent/UpdateTrunk.php`                       | ✅ Creado     |
| `app/Actions/Tenant/Agent/DeleteTrunk.php`                       | ✅ Creado     |
| `app/Actions/Tenant/Agent/ListTrunks.php`                        | ✅ Creado     |
| `app/Actions/Tenant/Agent/ShowTrunk.php`                         | ✅ Creado     |
| `app/Data/Tenant/Agent/CreateTrunkData.php`                      | ✅ Creado     |
| `app/Data/Tenant/Agent/UpdateTrunkData.php`                      | ✅ Creado     |
| `app/Http/Requests/Tenant/Agent/StoreTrunkRequest.php`           | ✅ Creado     |
| `app/Http/Requests/Tenant/Agent/UpdateTrunkRequest.php`          | ✅ Creado     |
| `app/Services/Tenant/Agent/Runner/RunnerRegistry.php`            | ✅ Creado     |
| `app/Services/Tenant/Agent/Runner/RunnerClient.php`              | ✅ Modificado |
| `config/runners.php`                                             | ✅ Modificado |
| `database/migrations/tenant/Agent/*_create_trunks_table.php`     | ✅ Creado     |
| `database/factories/tenant/Agent/TrunkFactory.php`               | ✅ Creado     |
| `routes/tenant/api/ai/trunks.php`                                | ✅ Creado     |
| `app/Models/Tenant/Agent/Agent.php`                              | ✅ Modificado |

### Runner (Python) ✅

| Archivo                                                     | Estado                       |
| ----------------------------------------------------------- | ---------------------------- |
| `services/runners/app/services/trunk_resolution_service.py` | ✅ Creado                    |
| `services/runners/app/services/runner_registry.py`          | ✅ Creado                    |
| `services/runners/app/core/config.py`                       | ✅ Modificado                |
| `services/runners/app/main.py`                              | ✅ Modificado                |
| `services/runners/app/services/trunk_service.py`            | ✅ Refactorizado (read-only) |
| `services/runners/app/api/v1/trunks.py`                     | ✅ Refactorizado (read-only) |

## Orden recomendado

**Fase 1 -> Fase 2E -> Fase 2F -> Fase 3 (completar)**

Fase 1 es prerequisito (desacopla Redis, arregla KEYS). Fase 2E y 2F son los items pendientes de Fase 2. Fase 3 está casi completa.
