import uuid
from datetime import datetime
from typing import Any, Dict, List, Literal, Optional, Union

from pydantic import BaseModel, Field

# ---------------------------------------------------------------------------
# VAD
# ---------------------------------------------------------------------------


class VADParams(BaseModel):
    confidence: float = 0.5
    start_secs: float = 0.2
    stop_secs: float = 0.8
    min_silence_duration_ms: Optional[int] = None
    min_volume: float = 0.6


class VADConfig(BaseModel):
    provider: Literal["silero", "webrtc"] = "silero"
    params: VADParams = Field(default_factory=VADParams)


# ---------------------------------------------------------------------------
# Knowledge base
# ---------------------------------------------------------------------------


class KnowledgeBaseConfig(BaseModel):
    id: str = ""
    enabled: bool = False
    provider: Literal["text_file", "pinecone", "pdf_url"] = "text_file"
    source_uri: Optional[str] = None
    index_name: Optional[str] = None
    namespace: Optional[str] = None
    description: Optional[str] = None
    retrieval_settings: Dict[str, Any] = Field(default_factory=dict)


# ---------------------------------------------------------------------------
# Agent / LLM brain
# ---------------------------------------------------------------------------


class LLMConfig(BaseModel):
    temperature: float = Field(0.7, ge=0.0, le=2.0)
    max_tokens: int = 4096
    top_p: float = 0.9


class AgentConfig(BaseModel):
    provider: Literal[
        "google",
        "openai",
        "anthropic",
        "groq",
        "together",
        "mistral",
        "aws",
        "ultravox",
    ] = "google"
    model: Optional[str] = None
    temperature: float = Field(0.7, ge=0.0, le=2.0)
    max_tokens: int = 4096
    top_p: float = 0.9
    system_prompt: str = "You are a helpful assistant."
    initial_messages: List[Dict[str, str]] = Field(default_factory=list)
    knowledge_base: Optional[KnowledgeBaseConfig] = None
    tools: List[Dict[str, Any]] = Field(default_factory=list)


# ---------------------------------------------------------------------------
# Inactivity / session limits
# ---------------------------------------------------------------------------


class InactivityStep(BaseModel):
    """One step in the inactivity escalation ladder."""

    message: Union[str, List[str]]
    wait_seconds: float = 15.0
    end_behavior: Literal["continue", "hangup"] = "continue"

    # Backward-compat alias used by BaseBot
    @property
    def timeout(self) -> float:
        return self.wait_seconds

    def model_dump(self, **kwargs):
        d = super().model_dump(**kwargs)
        # Normalise message to a single string for BaseBot compatibility
        if isinstance(d.get("message"), list):
            import random

            d["message"] = random.choice(d["message"])
        d["timeout"] = d.pop("wait_seconds", self.wait_seconds)
        return d


class InactivityMessage(BaseModel):
    """Legacy alias kept for backward compatibility."""

    message: str
    timeout: float = 10.0
    end_behavior: Literal["continue", "hangup"] = "continue"


class SessionLimits(BaseModel):
    max_duration_minutes: int = 30
    max_duration_message: str = (
        "Nuestra conversación ha llegado al límite de tiempo. ¡Hasta pronto!"
    )
    inactivity_enabled: bool = True
    inactivity_steps: List[InactivityStep] = Field(default_factory=list)
    inactivity_final_message: str = "Debido a la inactividad, tendré que terminar la llamada. ¡Que tengas un buen día!"
    inactivity_total_timeout_seconds: int = 60


# ---------------------------------------------------------------------------
# Pipeline / behaviour
# ---------------------------------------------------------------------------


class AmbientSoundConfig(BaseModel):
    audio: str = "office_ambience"
    volume: float = 0.3
    enabled: bool = False


class ThinkingSoundConfig(BaseModel):
    audio: str = "light_hum"
    volume: float = 0.5
    enabled: bool = True


class BehaviorConfig(BaseModel):
    interruptibility: bool = True
    initial_action: Literal["SPEAK_FIRST", "WAIT"] = "SPEAK_FIRST"
    streaming: bool = True
    ambient_sound: AmbientSoundConfig = Field(default_factory=AmbientSoundConfig)
    thinking_sound: ThinkingSoundConfig = Field(default_factory=ThinkingSoundConfig)


class PipelineSettings(BaseModel):
    vad: VADConfig = Field(default_factory=VADConfig)
    behavior: BehaviorConfig = Field(default_factory=BehaviorConfig)
    session_limits: SessionLimits = Field(default_factory=SessionLimits)

    # Convenience shorthands (kept for BaseBot compatibility)
    initial_message: Optional[str] = None
    initial_delay: float = 0.0
    initial_message_interruptible: bool = True

    # ---------------------------------------------------------------------------
    # Backward-compat properties consumed by BaseBot / CallService
    # ---------------------------------------------------------------------------

    @property
    def interruptibility(self) -> bool:
        return self.behavior.interruptibility

    @property
    def speak_first(self) -> bool:
        return self.behavior.initial_action == "SPEAK_FIRST"

    @property
    def inactivity_messages(self) -> List[Dict[str, Any]]:
        """Return inactivity steps in the format expected by BaseBot."""
        return [step.model_dump() for step in self.session_limits.inactivity_steps]


# ---------------------------------------------------------------------------
# Transport / IO layer
# ---------------------------------------------------------------------------


class TransportConfig(BaseModel):
    provider: Literal["daily", "twilio-websocket", "websocket"] = "daily"
    params: Dict[str, Any] = Field(default_factory=dict)


class STTConfig(BaseModel):
    provider: Literal["deepgram", "gladia", "assemblyai", "groq", "ultravox"] = (
        "deepgram"
    )
    model: Optional[str] = None
    language: str = "en"
    latency_mode: str = "interactive"
    params: Dict[str, Any] = Field(default_factory=dict)
    enable_mute_filter: bool = False


class TTSConfig(BaseModel):
    provider: Literal[
        "deepgram",
        "cartesia",
        "elevenlabs",
        "rime",
        "playht",
        "openai",
        "azure",
        "ultravox",
    ] = "cartesia"
    voice_id: Optional[str] = None
    model_id: Optional[str] = None
    latency_mode: str = "pvc_low_latency"
    params: Dict[str, Any] = Field(default_factory=dict)
    speed: float = 1.0
    language: str = "en"


class SipConfig(BaseModel):
    amd_enabled: bool = False
    amd_action_on_machine: Literal["hangup", "leave_message", "continue"] = "hangup"
    default_transfer_number: Optional[str] = None
    auth_token: Optional[str] = None
    sip_uri: Optional[str] = None
    caller_id: Optional[str] = None
    sip_headers: Dict[str, str] = Field(default_factory=dict)


class IOLayerConfig(BaseModel):
    type: Literal["webrtc", "sip"] = "webrtc"
    transport: TransportConfig = Field(default_factory=TransportConfig)
    stt: Optional[STTConfig] = None
    tts: Optional[TTSConfig] = None
    vad: VADConfig = Field(default_factory=VADConfig)
    sip: SipConfig = Field(default_factory=SipConfig)


# ---------------------------------------------------------------------------
# Capabilities
# ---------------------------------------------------------------------------


class ToolConfig(BaseModel):
    name: str
    type: Literal["function_call", "internal"] = "function_call"
    api_endpoint: Optional[str] = None
    timeout_ms: int = 3000
    requires_confirmation: bool = False
    disabled: bool = False


class GuardrailsConfig(BaseModel):
    pii_redaction: bool = False
    financial_compliance_check: bool = False
    sensitive_topics_filter: List[str] = Field(default_factory=list)
    strict_email_privacy: bool = False


class HandoffConfig(BaseModel):
    enabled: bool = False
    trigger_phrases: List[str] = Field(default_factory=list)
    method: Literal["sip_transfer", "webhook", "queue"] = "sip_transfer"
    target: Optional[str] = None
    context_transfer: bool = True


class CapabilitiesConfig(BaseModel):
    tools: List[ToolConfig] = Field(default_factory=list)
    mcp_servers: List[Dict[str, Any]] = Field(default_factory=list)
    guardrails: GuardrailsConfig = Field(default_factory=GuardrailsConfig)
    handoff: HandoffConfig = Field(default_factory=HandoffConfig)


# ---------------------------------------------------------------------------
# Resilience / architecture
# ---------------------------------------------------------------------------


class ResilienceConfig(BaseModel):
    type: str = "anthropic"
    fallback_provider: Optional[str] = None
    fallback_model: Optional[str] = None
    health_check_interval_ms: int = 5000


class ArchitectureConfig(BaseModel):
    type: Literal["simple", "flow", "multimodal", "pipeline"] = "simple"
    config: Dict[str, Any] = Field(default_factory=dict)

    @property
    def resilience(self) -> Optional[ResilienceConfig]:
        r = self.config.get("resilience")
        if r:
            return ResilienceConfig(**r)
        return None


# ---------------------------------------------------------------------------
# Observability / webhooks
# ---------------------------------------------------------------------------


class WebhookConfig(BaseModel):
    url: Optional[str] = None
    headers: Dict[str, str] = Field(default_factory=dict)
    events: List[str] = Field(default_factory=lambda: ["call_started", "call_ended"])


class ObservabilityConfig(BaseModel):
    logging_level: str = "info"
    tracing: Optional[str] = None
    webhooks: Optional[WebhookConfig] = None
    cost_tracking_enabled: bool = False
    daily_budget_usd: float = 50.0


# ---------------------------------------------------------------------------
# Compliance
# ---------------------------------------------------------------------------


class ComplianceConfig(BaseModel):
    data_retention_days: int = 30
    recording_storage: str = "s3-encrypted"
    gdpr_compliant: bool = True
    audit_logs_enabled: bool = True


# ---------------------------------------------------------------------------
# Metadata
# ---------------------------------------------------------------------------


class AgentMetadata(BaseModel):
    name: str = "New Agent"
    slug: Optional[str] = None
    description: Optional[str] = None
    tags: List[str] = Field(default_factory=list)
    language: str = "en"


# ---------------------------------------------------------------------------
# Aggregate root  (v3 schema)
# ---------------------------------------------------------------------------


class Assistant(BaseModel):
    # Identity
    id: str = Field(default_factory=lambda: str(uuid.uuid4()), alias="agent_id")
    version: str = "3.0.0"
    created_at: datetime = Field(default_factory=datetime.utcnow)

    # Top-level metadata
    metadata: AgentMetadata = Field(default_factory=AgentMetadata)

    # Architecture
    architecture: ArchitectureConfig = Field(default_factory=ArchitectureConfig)

    # Brain (LLM + knowledge)
    agent: AgentConfig = Field(default_factory=AgentConfig)

    # Runtime profiles (STT / TTS / VAD / behaviour / session limits)
    io_layer: IOLayerConfig = Field(default_factory=IOLayerConfig)
    pipeline_settings: PipelineSettings = Field(default_factory=PipelineSettings)

    # Capabilities (tools, guardrails, handoff)
    capabilities: CapabilitiesConfig = Field(default_factory=CapabilitiesConfig)

    # Observability & compliance
    observability: ObservabilityConfig = Field(default_factory=ObservabilityConfig)
    compliance: ComplianceConfig = Field(default_factory=ComplianceConfig)

    # Flow config (for flow-type bots)
    flow: Optional[Dict[str, Any]] = None

    # ---------------------------------------------------------------------------
    # Convenience shorthands (backward compat with existing services)
    # ---------------------------------------------------------------------------

    @property
    def name(self) -> str:
        return self.metadata.name

    @property
    def description(self) -> Optional[str]:
        return self.metadata.description

    @property
    def architecture_type(self) -> str:
        t = self.architecture.type
        # "pipeline" maps to "simple" internally
        return t if t in ("simple", "flow", "multimodal") else "simple"

    @property
    def webhooks(self) -> Optional[WebhookConfig]:
        return self.observability.webhooks

    @property
    def system_prompt(self) -> str:
        return self.agent.system_prompt

    @property
    def llm_provider(self) -> str:
        return self.agent.provider

    @property
    def llm_model(self) -> Optional[str]:
        return self.agent.model

    @property
    def llm_temperature(self) -> float:
        return self.agent.temperature

    @property
    def stt_provider(self) -> Optional[str]:
        return self.io_layer.stt.provider if self.io_layer.stt else None

    @property
    def tts_provider(self) -> Optional[str]:
        return self.io_layer.tts.provider if self.io_layer.tts else None

    @property
    def tts_voice(self) -> Optional[str]:
        return self.io_layer.tts.voice_id if self.io_layer.tts else None
