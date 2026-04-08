from typing import List

from app.domains.assistant.models.assistant import Assistant


def dict_to_cli_args(assistant: Assistant) -> List[str]:
    """Convert an Assistant to CLI arguments for the bot session manager."""
    args: List[str] = []

    args.extend(["--architecture-type", assistant.architecture_type])

    if assistant.name:
        args.extend(["--bot-name", assistant.name])

    # LLM
    if assistant.agent.provider:
        args.extend(["--llm-provider", assistant.agent.provider])
    if assistant.agent.model:
        args.extend(["--llm-model", assistant.agent.model])
    if assistant.agent.temperature is not None:
        args.extend(["--llm-temperature", str(assistant.agent.temperature)])

    # TTS
    if assistant.io_layer.tts:
        if assistant.io_layer.tts.provider:
            args.extend(["--tts-provider", assistant.io_layer.tts.provider])
        if assistant.io_layer.tts.voice_id:
            args.extend(["--tts-voice", assistant.io_layer.tts.voice_id])

    # STT
    if assistant.io_layer.stt:
        if assistant.io_layer.stt.provider:
            args.extend(["--stt-provider", assistant.io_layer.stt.provider])
        if assistant.io_layer.stt.enable_mute_filter:
            args.extend(["--enable-stt-mute-filter", "true"])

    # SIP / AMD
    if assistant.io_layer.sip.amd_enabled:
        args.extend(["--amd-enabled", "true"])

    # System prompt
    if assistant.agent.system_prompt:
        args.extend(["--system-prompt", assistant.agent.system_prompt])

    # Speak first
    speak_first = assistant.pipeline_settings.speak_first
    args.extend(["--speak-first", "true" if speak_first else "false"])

    return args
