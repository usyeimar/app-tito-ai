export type AgentBrainConfig = {
    welcome_message?: string;
    llm?: {
        provider?: string;
        model?: string;
        instructions?: string;
        config?: {
            temperature?: number;
            max_tokens?: number;
            top_p?: number;
        };
    };
    localization?: {
        default_locale?: string;
        timezone?: string;
        currency?: string;
        number_format?: string;
    };
    context?: {
        strategy?: string;
        max_tokens?: number;
        min_messages?: number;
        enabled?: boolean;
    };
    [key: string]: unknown;
};

export type AgentRuntimeConfig = {
    // STT
    stt_provider?: string;
    stt_model?: string;
    stt_language?: string;
    stt_keywords?: string;
    // TTS
    tts_provider?: string;
    tts_model?: string;
    tts_voice?: string;
    tts_voice_id?: string;
    tts_speed?: number;
    tts_buffer_size?: number;
    // Engine / Behavior
    interruptibility?: boolean;
    initial_action?: string;
    streaming?: boolean;
    inactivity_timeout_enabled?: boolean;
    inactivity_timeout_seconds?: number;
    inactivity_messages?: string[];
    inactivity_final_message?: string;
    max_duration_seconds?: number;
    // Call / Telephony
    telephony_provider?: string;
    ambient_noise?: string;
    noise_cancellation?: boolean;
    voicemail_detection?: boolean;
    keypad_input_dtmf?: boolean;
    auto_reschedule?: boolean;
    outbound_timing_restrictions?: boolean;
    final_call_message?: string;
    hangup_on_silence_enabled?: boolean;
    hangup_on_silence_seconds?: number;
    total_call_timeout?: number;
    // Post Call
    webhook_url?: string;
    summarization?: boolean;
    [key: string]: unknown;
};

export type AgentTool = {
    name: string;
    description: string;
    parameters?: Record<string, unknown>;
    disabled?: boolean;
};

export type AgentVariable = {
    name: string;
    value: string;
    description?: string;
};

export type Agent = {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    language: string;
    tags: string[] | null;
    timezone: string;
    currency: string;
    number_format: string;
    knowledge_base_id: string | null;
    brain_config: AgentBrainConfig | null;
    runtime_config: AgentRuntimeConfig | null;
    architecture_config: Record<string, unknown> | null;
    capabilities_config: Record<string, unknown> | null;
    observability_config: Record<string, unknown> | null;
    variables: AgentVariable[];
    created_at: string | null;
    updated_at: string | null;
};

export type TenantSummary = {
    id: string;
    name: string;
    slug: string;
};
