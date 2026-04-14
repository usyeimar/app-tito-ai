# TODO — Evolución del Agent Manifest a `schema_version: 2.0.0`

**Contexto:** los manifests actuales (`docs/resources/agent-*.json`) tienen problemas estructurales que limitan operabilidad, i18n, seguridad y reuso. Este plan evoluciona el schema de `1.0.0` → `2.0.0` con retrocompatibilidad vía adapter.

**Estado inicial (2026-04-14):**
- 7 manifests parsean contra `app/schemas/agent.py` (fix `tenant_id` + `Literal` providers ya aplicado)
- E2E SIP/ARI validado
- Problema principal: prompts monolíticos, secretos embebidos, sin preset por canal, compliance binaria

---

## Sprint 1 — Fundamentos (prompts + secretos + locale)

### 1.1 Prompts externalizados (`ref://`)

- [ ] Crear `docs/prompts/` con estructura:
  ```
  docs/prompts/
    common/
      compliance-co.md
      tono-colombiano.md
      channel-sip-restrictions.md
    sofia-sip.md
    alex-webrtc.md
    luna-widget.md
  ```
- [ ] Nuevo schema en `app/schemas/agent.py`:
  ```python
  class BrainPrompt(BaseModel):
      template: str  # "ref://prompts/sofia.md@v3" o string inline (retrocompat)
      fragments: List[str] = []
      variables: Dict[str, Any] = {}
  ```
- [ ] `BrainLLM.instructions`: aceptar `Union[str, BrainPrompt]`
- [ ] Resolver `ref://` en `agent_resolution_service`:
  - `ref://prompts/X.md@v3` → lee del filesystem o del backend Laravel
  - Inline (con `\n`) → retrocompat, funciona igual
- [ ] Test: mismo agente con prompt inline vs `ref://` produce misma pipeline

### 1.2 Secretos por referencia

- [ ] Schema:
  ```python
  class BrainLLM(...):
      api_key: Optional[str] = None        # deprecar (warning al usar)
      api_key_ref: Optional[str] = None    # "vault://tenant/X/openai" o "env://OPENAI_API_KEY"
  ```
- [ ] Resolver de refs: soportar `env://`, `vault://` (stub primero), `secret://` (k8s)
- [ ] Log warning si algún manifest usa `api_key` literal
- [ ] Audit: grep por `sk-`, `Bearer` en Redis — no debe aparecer

### 1.3 Unificar locale (BCP-47)

- [ ] Eliminar `metadata.language`, introducir `metadata.locale: str`
- [ ] Adapter: si viene v1 con `language: "es"`, default `locale: "es-CO"` (con warning)
- [ ] `brain.localization.default_locale` queda, pero con validator que exige coincidencia con `metadata.locale`
- [ ] Actualizar los 7 manifests para usar `locale: "es-CO"` consistente

---

## Sprint 2 — Presets por canal + lifecycle

### 2.1 Preset inheritance (`extends`)

- [ ] Crear `docs/presets/`:
  ```
  docs/presets/
    sip-inbound-co-v1.json   # 8kHz, slin, phonecall model, max_dur=1200s
    webrtc-premium-v1.json   # HD audio, recording, kb_enabled
    widget-embed-v1.json     # WAIT_FOR_USER, timeout 500ms, no recording
  ```
- [ ] Schema root:
  ```python
  class AgentConfig(...):
      extends: Optional[str] = None  # "preset://sip-inbound-co-v1"
  ```
- [ ] Merge strategy (deep merge + list semantics):
  - `tools[+]` = append
  - `tools[=]` = replace
  - default = override de claves a nivel hoja
- [ ] Refactor los 3 manifests nuevos (sip/webrtc/widget) a usar `extends` — validar que el agente resultante coincide byte-a-byte con el pre-refactor

### 2.2 Lifecycle + Rollout

- [ ] Nuevo bloque:
  ```python
  class Lifecycle(BaseModel):
      status: Literal["draft", "active", "paused", "deprecated"] = "draft"
      created_at: datetime
      updated_at: datetime
      updated_by: Optional[str] = None
      parent_version: Optional[str] = None  # agent_id@version previo

  class Rollout(BaseModel):
      traffic_percentage: int = Field(100, ge=0, le=100)
      kill_switch: bool = False
      canary_of: Optional[str] = None  # agent_id base del canary
  ```
- [ ] `agent_resolution_service`: si `kill_switch=True` o `status=paused|deprecated` → rechazar con error claro antes de llegar al pipeline
- [ ] Endpoint `POST /api/v1/agents/{id}/pause` que setea `kill_switch=true`

---

## Sprint 3 — Tools robustas + Compliance granular

### 3.1 Tool execution metadata

- [ ] Extender `ToolDefinition`:
  ```python
  class ToolExecution(BaseModel):
      timeout_ms: int = 10000
      retries: ToolRetry = ToolRetry()  # max, backoff_ms
      idempotency_key_from: List[str] = []  # nombres de params
      auth_scope: Optional[str] = None
      requires_confirmation: bool = False
      on_failure: Literal["retry", "escalate_to_human", "fail_silently"] = "retry"

  class ToolDefinition(...):
      execution: Optional[ToolExecution] = None
  ```
- [ ] Wire en `ServiceFactory`: honrar timeout_ms y on_failure
- [ ] Test: tool que tarda > timeout → pipeline no cuelga, bot dice "sigo procesando..."

### 3.2 Compliance rico

- [ ] Reemplazar `ComplianceConfig` binaria:
  ```python
  class PIIRedactionRules(BaseModel):
      redact: List[Literal["phone", "email", "cedula", "card_pan", "ssn", "address"]] = []
      strategy: Literal["mask", "remove", "hash"] = "mask"
      preserve_last: int = 4  # dígitos visibles

  class RecordingConfig(BaseModel):
      audio: bool = False
      retention_days: int = 30
      region: Optional[str] = None

  class ConsentConfig(BaseModel):
      required: bool = False
      disclosure_text_ref: Optional[str] = None

  class ComplianceConfig(BaseModel):
      pii: PIIRedactionRules = PIIRedactionRules()
      recording: RecordingConfig = RecordingConfig()
      consent: ConsentConfig = ConsentConfig()
  ```
- [ ] Adapter v1: `pii_redaction: true` → `pii.redact: ["phone","email","cedula","card_pan"]`, `strategy: "mask"`
- [ ] Adapter v1: `record_audio: true` → `recording.audio: true, retention_days: 30`

---

## Sprint 4 — Transport rico + i18n

### 4.1 Transport config por provider

- [ ] Discriminated union en `RuntimeTransport`:
  ```python
  class SipTransportConfig(BaseModel):
      codec_preferences: List[str] = ["opus","ulaw","alaw"]
      sample_rate_hz: Literal[8000, 16000] = 8000
      dtmf_mode: Literal["rfc2833","info","inband"] = "rfc2833"
      stir_shaken: Optional[str] = None
      max_ring_seconds: int = 30

  class LiveKitTransportConfig(BaseModel):
      room_ttl_seconds: int = 3600
      enable_video: bool = False
      enable_screen_share: bool = False
      audio_bitrate_kbps: int = 64
      region: Optional[str] = None

  class WidgetTransportConfig(BaseModel):
      path_prefix: str = "/ws/widget"
      origin_allowlist: List[str] = []
      widget_theme_ref: Optional[str] = None
      welcome_delay_ms: int = 0

  RuntimeTransport = Annotated[
      Union[SipTransport, LiveKitTransport, DailyTransport, WidgetTransport],
      Field(discriminator="provider")
  ]
  ```
- [ ] Wire en `transport_setup.py` y `tito_ari_manager.py`
- [ ] CORS en FastAPI widget WS: leer `origin_allowlist`

### 4.2 Messages i18n

- [ ] Cualquier campo "mensaje al usuario" debe soportar:
  ```python
  I18nMessage = Union[str, Dict[str, str]]  # str = locale único
  ```
- [ ] Campos afectados: `inactivity_timeout.final_message`, `inactivity_timeout.steps[].message`, `tools[].processing_message`
- [ ] Resolver elige `locale` del agente; fallback a primera clave disponible
- [ ] Alternativa: `ref://messages/X.yml` con estructura `{locale: {key: value}}`

### 4.3 Semántica explícita de `message: List[str]`

- [ ] `InactivityTimeoutStep.message` → reemplazar por:
  ```python
  messages: List[I18nMessage]
  selection: Literal["random", "sequential", "first"] = "first"
  ```

---

## Sprint 5 — Observability, Evaluation, Budget

### 5.1 Observability avanzada

- [ ] Nuevo bloque:
  ```python
  class ObservabilityConfig(BaseModel):
      log_level: Literal["DEBUG","INFO","WARN","ERROR"] = "INFO"
      trace_sampling_rate: float = Field(1.0, ge=0, le=1)
      otlp_endpoint: Optional[str] = None
      metrics_enabled: bool = False
      log_redaction_rules: List[str] = []  # regex a redactar en logs
      alerts: Dict[str, str] = {}  # {"tool_timeout": "pagerduty://..."}
  ```

### 5.2 Evaluation hooks

- [ ] Bloque opcional:
  ```python
  class EvaluationConfig(BaseModel):
      dataset_id: Optional[str] = None
      golden_turns_ref: Optional[str] = None  # "ref://evals/sofia-v1.yml"
      regression_on_deploy: bool = False
  ```
- [ ] CI hook: al publicar, correr goldens → bloquear si regressión > threshold

### 5.3 Budget guardrails

- [ ] Bloque opcional:
  ```python
  class BudgetConfig(BaseModel):
      max_cost_usd_per_session: Optional[float] = None
      max_tokens_per_session: Optional[int] = None
      max_tool_calls_per_session: Optional[int] = None
      on_limit: Literal["cutoff","warn","soft_handoff"] = "warn"
  ```
- [ ] Contador en `PipelineTask`, cortar sesión al pasar umbral

---

## Sprint 6 — Migración + retrocompatibilidad

### 6.1 Adapter v1 → v2

- [ ] `app/schemas/agent_v1_adapter.py`:
  - Entrada: dict de schema v1
  - Salida: instancia `AgentConfigV2` con los defaults/transformaciones descritos arriba
  - Warnings estructurados por cada transformación (para ir migrando manifests originales)
- [ ] `agent_resolution_service`: detectar `schema_version` — si falta o es `1.0.0`, usar adapter; si `2.0.0`, usar directamente
- [ ] Tests unitarios: cada manifest v1 del repo pasa por adapter y produce v2 válido

### 6.2 Migrar los 7 manifests existentes a v2

- [ ] `agent-mvp-manifest.json` → v2 + `extends: preset://widget-embed-v1`
- [ ] `agent-flow-manifest.json` → v2 + `extends: preset://webrtc-premium-v1`
- [ ] `agent-pipeline-manifest.json` → v2
- [ ] `agent-unified-manifest.json` → v2
- [ ] `agent-sip-manifest.json` → v2 + `extends: preset://sip-inbound-co-v1`
- [ ] `agent-webrtc-manifest.json` → v2 + `extends: preset://webrtc-premium-v1`
- [ ] `agent-widget-manifest.json` → v2 + `extends: preset://widget-embed-v1`

### 6.3 Deprecación v1

- [ ] Fecha target: 3 meses post-merge de v2
- [ ] Log warning con `agent_id` cada vez que el resolver use el adapter v1
- [ ] Después de la fecha target: adapter sigue funcionando pero con warning ERROR

---

## Verificación por sprint

### Sprint 1
- [ ] `pytest tests/schemas/test_agent_v2_prompt_ref.py` pasa
- [ ] Seed de Redis con `ref://` funciona en E2E ARI (SIP test)
- [ ] Grep de `sk-` en Redis: 0 resultados

### Sprint 2
- [ ] Un manifest de 30 líneas con `extends` produce el mismo AgentConfig que uno de 150 líneas sin `extends`
- [ ] `kill_switch=true` → llamada rechazada antes de Stasis

### Sprint 3
- [ ] Tool con `timeout_ms: 1000` que duerme 5s → pipeline no cuelga, bot habla "sigo procesando"
- [ ] `pii.redact=["cedula"]` → transcript guardado enmascara cédulas

### Sprint 4
- [ ] Un mismo agente, cambiando solo `locale`, habla en `es-CO` vs `es-MX` vs `en-US`
- [ ] Widget con `origin_allowlist` rechaza WS desde dominio no whitelisted

### Sprint 5
- [ ] Traces en OTLP collector con sampling 10%
- [ ] Deploy con regresión en goldens → CI rojo

### Sprint 6
- [ ] Los 7 manifests v1 pasan por adapter sin errores
- [ ] Los 7 manifests v2 (nuevos) tienen <60% del tamaño de los v1 gracias a presets

---

## Riesgos y decisiones pendientes

1. **`ref://` backend**: ¿resolver contra filesystem del runner, o pull desde Laravel vía API? Decisión: empezar filesystem local (presets), después backend para prompts tenant-específicos.
2. **Cache de refs**: prompts de 5KB + resolver en cada sesión = latencia. Necesita cache con TTL. Laravel ya tiene `AgentRedisSyncService` — mismo patrón.
3. **Versionado de presets**: `preset://sip-inbound-co-v1` explícito. Cambiar defaults rompe agentes — obliga crear `v2`.
4. **Discriminated unions en Pydantic**: cuidado con el `model_dump()` para persistir — verificar round-trip.
5. **`extends` recursivo**: ¿un preset puede extender a otro? Decisión MVP: no, solo 1 nivel. Si hace falta, después.

---

## Métricas de éxito

- **Tamaño medio de manifest**: hoy ~150 líneas → target <60 líneas post-Sprint 2.
- **Tiempo para crear un nuevo agente SIP**: hoy ~30 min (copy-paste + ajustes) → target <5 min (extend preset + prompt ref).
- **Incidentes por secret leak**: 0 (hoy: posible si alguien commitea con `api_key` literal).
- **Tiempo para rollback de prompt malo**: hoy requiere redeploy de config → target <1 min via `rollout.kill_switch` o revertir ref.
