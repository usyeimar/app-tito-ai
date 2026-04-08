from enum import Enum


class ServiceProviders(str, Enum):
    # LLM
    OPENAI = "openai"
    GROQ = "groq"
    ANTHROPIC = "anthropic"
    OPENROUTER = "openrouter"
    GOOGLE = "google"
    TOGETHER = "together"
    MISTRAL = "mistral"
    ULTRAVOX = "ultravox"

    # STT
    DEEPGRAM = "deepgram"
    CARTESIA = "cartesia"
    ASSEMBLYAI = "assemblyai"
    GLADIA = "gladia"
    AZURE = "azure"
    GOOGLE_STT = "google"
    OPENAI_STT = "openai"

    # TTS
    ELEVENLABS = "elevenlabs"
    RIME = "rime"
    PLAYHT = "playht"
    AWS = "aws"
    # DEEPGRAM, CARTESIA, OPENAI, AZURE, GOOGLE also used for TTS
