from typing import Optional, List, Dict, Any, Literal, Union
from pydantic import BaseModel, Field, ConfigDict, JsonValue


class AgentMetadata(BaseModel):
    """Metadata identifying the agent and its basic properties."""
    name: str = Field(..., description="The user-friendly name of the agent.", examples=["Luna Travel Assistant"])
    slug: str = Field(..., description="A unique URL-friendly identifier.", examples=["luna-travel-v3"])
    description: str = Field(..., description="A brief summary of the agent's purpose.")
    tags: List[str] = Field(default_factory=list, description="Categorization tags for search and filtering.")
    language: str = Field(..., description="The primary ISO language code of the agent.", examples=["es-MX"])


class ArchitectureConfigResilience(BaseModel):
    """Configuration for service resilience and fallbacks."""
    type: str = Field(..., description="Resilience strategy type, e.g., 'failover'.")
    fallback_provider: Optional[str] = Field(None, description="Provider to use if the primary fails.")
    fallback_model: Optional[str] = Field(None, description="Model ID to use for the fallback provider.")
    health_check_interval_ms: Optional[int] = Field(None, description="Interval in milliseconds between health checks.")


class ArchitectureConfig(BaseModel):
    """High-level architectural configuration settings."""
    resilience: Optional[ArchitectureConfigResilience] = Field(None, description="Redundancy and recovery settings.")


class Architecture(BaseModel):
    """Defines the core execution architecture for the agent."""
    type: Literal["pipeline", "node-graph"] = Field(..., description="The movement and processing logic type.")
    config: Optional[ArchitectureConfig] = Field(None, description="Architecture-specific configuration.")


class BrainLLMConfig(BaseModel):
    """Configuration options for the Large Language Model."""
    temperature: Optional[float] = Field(0.5, ge=0, le=2, description="Controls randomness of response.")
    max_tokens: Optional[int] = Field(4096, description="Upper limit on the number of generated tokens.")
    top_p: Optional[float] = Field(0.9, description="Nucleus sampling probability.")


class BrainLLM(BaseModel):
    """The core intelligence module of the agent."""
    provider: str = Field(..., description="LLM service provider (e.g., 'openai').", examples=["openai", "anthropic"])
    model: str = Field(..., description="Specific model ID.", examples=["gpt-4o", "claude-3-5-sonnet"])
    config: Optional[BrainLLMConfig] = Field(default_factory=BrainLLMConfig)
    instructions: str = Field(..., description="System prompt or persona guiding the agent's behavior.")
    api_key: Optional[str] = Field(None, description="Optional per-agent API key.")


class BrainKnowledgeBase(BaseModel):
    """RAG configuration for grounding the agent's knowledge."""
    id: str = Field(..., description="The unique ID of the linked knowledge base.")


class BrainLocalization(BaseModel):
    """Regional and formatting settings."""
    default_locale: str = Field(..., description="Primary locale code.", examples=["en-US"])
    timezone: str = Field(..., description="IANA timezone name.", examples=["UTC", "America/New_York"])
    currency: str = Field(..., description="ISO 4217 Currency code.", examples=["USD"])
    number_format: str = Field(..., description="Template for formatting numbers.")


class BrainContext(BaseModel):
    """Configuration for managing the LLM conversation history (context)."""
    strategy: Literal["summarize", "truncate", "none"] = Field("none", description="Strategy to keep context size manageable.")
    max_tokens: int = Field(4000, description="Token limit before triggering context reduction.")
    min_messages: int = Field(4, description="Minimum number of recent messages to always keep unsummarized/preserved.")
    enabled: bool = Field(False, description="Whether to enable automatic context management.")


class Brain(BaseModel):
    """Encapsulates the agent's cognitive capabilities: logic, memory, and localization."""
    llm: BrainLLM
    knowledge_base: Optional[BrainKnowledgeBase] = Field(None, description="External knowledge sources (RAG).")
    localization: Optional[BrainLocalization] = Field(None, description="Regional formatting and context.")
    context: BrainContext = Field(default_factory=BrainContext)


class RuntimeSTT(BaseModel):
    """Speech-to-Text configuration."""
    provider: str = Field(..., description="Provider (e.g., 'deepgram').", examples=["deepgram", "google"])
    model: str = Field(..., description="Model identifier.", examples=["nova-2", "base"])
    latency_mode: Optional[str] = Field(None, description="Mode for optimizing latency.")
    api_key: Optional[str] = Field(None, description="STT-specific API key.")
    language: Optional[str] = Field(None, description="Optional language override for the STT.")


class RuntimeTTS(BaseModel):
    """Text-to-Speech configuration."""
    provider: str = Field(..., description="Provider (e.g., 'cartesia').", examples=["cartesia", "elevenlabs"])
    voice_id: str = Field(..., description="ID of the voice to use.")
    model_id: Optional[str] = Field(None, description="Specific model ID for the voice engine.")
    latency_mode: Optional[str] = Field(None, description="Latency vs. Quality optimization mode.")
    api_key: Optional[str] = Field(None, description="TTS-specific API key.")
    speed: Optional[float] = Field(1.0, description="Speech speed multiplier.")


class RuntimeVADParams(BaseModel):
    """Fine-tuning parameters for Voice Activity Detection."""
    confidence: float = Field(0.7, description="Threshold for speech detection.")
    start_secs: float = Field(0.2, description="Minimum duration of speech to trigger a SPEAKING state.")
    stop_secs: float = Field(0.2, description="Silence duration required to transition back to QUIET.")
    min_volume: float = Field(0.6, description="Minimum volume threshold for VAD.")


class RuntimeVAD(BaseModel):
    """Voice Activity Detection settings."""
    provider: str = Field(..., description="VAD technology (e.g., 'silero').")
    params: Optional[RuntimeVADParams] = Field(default_factory=RuntimeVADParams)


class RuntimeTransport(BaseModel):
    """Transport layer configuration."""
    provider: str = Field("livekit", description="Transport provider ('livekit' or 'daily').")


class RuntimeBehaviorSound(BaseModel):
    """Configuration for triggered or ambient sounds."""
    audio: str = Field(..., description="Path or URL to the audio file.")
    volume: float = Field(..., ge=0, le=1, description="Volume level (0-1).")
    enabled: bool = Field(True, description="Whether this sound is active.")


class RuntimeBehavior(BaseModel):
    """Interaction dynamics and personality settings."""
    interruptibility: bool = Field(True, description="Can the user interrupt the agent?")
    initial_action: str = Field(..., description="First action code or prompt script.")
    streaming: bool = Field(True, description="Use streaming responses for LLM/TTS.")
    ambient_sound: Optional[RuntimeBehaviorSound] = Field(None, description="Background audio loop.")
    thinking_sound: Optional[RuntimeBehaviorSound] = Field(None, description="Sound played while processing.")
    user_mute_strategies: List[str] = Field(
        default_factory=list, 
        description="List of strategies to mute user input (e.g., 'first_speech', 'function_call', 'always', 'until_first_bot_complete')."
    )
    turn_detection_strategy: Literal["smart", "timeout"] = Field("smart", description="Strategy to detect when user finishes speaking.")
    turn_detection_timeout_ms: int = Field(600, description="Used only if strategy is 'timeout'. Milliseconds of silence to wait.")
    smart_turn_stop_secs: float = Field(2.0, description="Used only if strategy is 'smart'. Seconds of silence to wait before declating turn complete.")


class InactivityTimeoutStep(BaseModel):
    """A single step in the inactivity timeout progression."""
    wait_seconds: int = Field(15, description="Seconds to wait before this step.")
    message: List[str] = Field(default_factory=lambda: ["¿Hola?"], description="Messages to speak.")


class InactivityTimeout(BaseModel):
    """Configuration for handling user silence."""
    enabled: bool = Field(False, description="Whether inactivity detection is active.")
    steps: List[InactivityTimeoutStep] = Field(default_factory=list, description="Sequence of warning steps.")
    final_message: str = Field("Cerrando por inactividad.", description="Last message before ending.")


class RuntimeSessionLimits(BaseModel):
    """Constraints and timeouts for a conversation session."""
    inactivity_timeout: Optional[InactivityTimeout] = Field(None, description="Inactivity handling.")
    max_duration_seconds: Optional[int] = Field(None, description="Maximum session duration.")


class RuntimeProfiles(BaseModel):
    """The technical stack active during a conversation session."""
    stt: RuntimeSTT
    tts: RuntimeTTS
    vad: Optional[RuntimeVAD] = Field(None, description="Optional VAD override.")
    transport: Optional[RuntimeTransport] = Field(None, description="Transport layer configuration.")
    behavior: Optional[RuntimeBehavior] = Field(None, description="Interaction logic.")
    session_limits: Optional[RuntimeSessionLimits] = Field(None, description="Session constraints.")


class ToolDefinition(BaseModel):
    """A function calling tool definition."""
    name: str = Field(..., description="The name of the tool/function.")
    processing_message: Optional[str] = Field(None, description="A friendly message to speak when the tool is executing (e.g. 'consultando su saldo').")
    description: Optional[str] = Field(None, description="Human-readable explanation.")
    parameters: Optional[Dict[str, JsonValue]] = Field(None, description="JSON Schema for the tool parameters.")
    disabled: bool = Field(False, description="Whether this tool is inactive.")


class AgentCapabilities(BaseModel):
    """Available functionalities like function calling tools."""
    tools: List[ToolDefinition] = Field(default_factory=list, description="Registered tools.")


class OrchestrationConfig(BaseModel):
    """Control over session routing and state management."""
    routing_logic: Optional[str] = Field(None, description="Rule-set for session orchestration.")
    session_context: Dict[str, JsonValue] = Field(default_factory=dict, description="Metadata for the session context.")


class ComplianceConfig(BaseModel):
    """Privacy, regulatory, and safety requirements."""
    pii_redaction: bool = Field(False, description="Remove personal info from transcripts.")
    record_audio: bool = Field(False, description="Save call recordings.")


class ObservabilityConfig(BaseModel):
    """Monitoring, logging, and performance tracking."""
    log_level: str = Field("INFO", description="Granularity of session logs.")
    metrics_enabled: bool = Field(False, description="Track latency and usage stats.")


class AgentConfig(BaseModel):
    """Root configuration model for a Tito AI Agent."""
    version: str = Field(..., description="Schema version identifier.", examples=["1.0.0"])
    agent_id: str = Field(..., description="Global unique identifier for the agent instance.")
    tenant_id: str = Field(..., description="Unique identifier for the tenant/organization.")
    callback_url: Optional[str] = Field(None, description="Custom URL to report session events (overrides default).")
    metadata: AgentMetadata
    architecture: Optional[Architecture] = Field(None, description="Core execution logic.")
    brain: Brain
    runtime_profiles: RuntimeProfiles
    capabilities: Optional[AgentCapabilities] = Field(default_factory=AgentCapabilities)
    orchestration: Optional[OrchestrationConfig] = Field(default_factory=OrchestrationConfig)
    compliance: Optional[ComplianceConfig] = Field(default_factory=ComplianceConfig)
    observability: Optional[ObservabilityConfig] = Field(default_factory=ObservabilityConfig)

    model_config = ConfigDict(
        populate_by_name=True,
        json_schema_extra={
            "example": {
                "version": "1.0.0",
                "agent_id": "uuid-1234",
                "metadata": {
                    "name": "Luna",
                    "slug": "luna",
                    "description": "Travel Expert",
                    "tags": ["travel"],
                    "language": "en"
                },
                "brain": {"llm": {"provider": "openai", "model": "gpt-4o", "instructions": "You are Luna."}},
                "runtime_profiles": {
                    "stt": {"provider": "deepgram", "model": "nova-2"},
                    "tts": {"provider": "cartesia", "voice_id": "voice-id"}
                }
            }
        }
    )
