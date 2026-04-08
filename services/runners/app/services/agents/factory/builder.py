import os
import logging
from typing import List, Dict, Optional, Any

# LLM Imports
from pipecat.services.openai.llm import OpenAILLMService
from pipecat.services.groq.llm import GroqLLMService
from pipecat.services.anthropic.llm import AnthropicLLMService
from pipecat.services.google.llm import GoogleLLMService
from pipecat.services.together.llm import TogetherLLMService
from pipecat.services.mistral.llm import MistralLLMService
from pipecat.services.ultravox.llm import UltravoxRealtimeLLMService

# TTS Imports
from pipecat.services.openai.tts import OpenAITTSService
from pipecat.services.google.tts import GoogleTTSService
from pipecat.services.deepgram.tts import DeepgramTTSService
from pipecat.services.cartesia.tts import CartesiaTTSService
from pipecat.services.elevenlabs.tts import ElevenLabsTTSService
from pipecat.services.rime.tts import RimeHttpTTSService
# PlayHTTTSService removed from core pipecat-ai, keeping placeholder or assuming custom if needed
from pipecat.services.azure.tts import AzureTTSService
from pipecat.services.aws.tts import AWSPollyTTSService

# STT Imports
from pipecat.services.openai.stt import OpenAISTTService
from pipecat.services.google.stt import GoogleSTTService
from pipecat.services.deepgram.stt import DeepgramSTTService
from pipecat.services.cartesia.stt import CartesiaSTTService
from pipecat.services.assemblyai.stt import AssemblyAISTTService
from pipecat.services.gladia.stt import GladiaSTTService
from pipecat.services.azure.stt import AzureSTTService

from pipecat.transcriptions.language import Language

from app.services.agents.factory.providers import ServiceProviders
from app.services.agents.factory.helpers import CustomDeepgramSTTService
from app.services.agents.factory.languages import resolve_language
from app.schemas.agent import AgentConfig

logger = logging.getLogger(__name__)


class AudioConfig:
    """Mock audio configuration if not provided."""
    transport_in_sample_rate = 16000
    transport_out_sample_rate = 16000


class ServiceFactory:
    """
    Factory for dynamically creating Pipecat services (STT, TTS, LLM) 
    based on AgentConfig and environment variables.
    """

    @staticmethod
    def create_stt_service(config: AgentConfig, audio_config: AudioConfig = AudioConfig()):
        stt_config = config.runtime_profiles.stt
        api_key = stt_config.api_key or os.getenv(f"{stt_config.provider.upper()}_API_KEY")

        if not api_key:
            raise ValueError(f"API Key missing for STT provider: {stt_config.provider}")

        logger.info(
            f"Creating STT service: provider={stt_config.provider}, model={stt_config.model}"
        )

        provider = stt_config.provider.lower()

        if provider == ServiceProviders.DEEPGRAM.value:
            # Enhanced Deepgram configuration
            keywords = []
            stt_keywords_env = os.getenv("STT_KEYWORDS")
            if stt_keywords_env:
                keywords = stt_keywords_env.split(",")

            # Resolver idioma con seudónimos (ej: "español" -> "es", "auto" -> detect)
            resolved_lang = resolve_language(stt_config.language) if stt_config.language else None
            detect_language = resolved_lang is None  # auto/multi/detect -> None
            language = resolved_lang.value if resolved_lang else None

            options = {
                "model": stt_config.model or "nova-2",
                "smart_format": True,
                "interim_results": True,
                "endpointing": 300,
            }

            if keywords:
                options["keywords"] = keywords
            if language:
                options["language"] = language

            return CustomDeepgramSTTService(
                api_key=api_key,
                settings=DeepgramSTTService.Settings(**options),
            )

        elif provider == ServiceProviders.CARTESIA.value:
            return CartesiaSTTService(
                api_key=api_key,
                sample_rate=audio_config.transport_in_sample_rate,
            )

        elif provider == ServiceProviders.ASSEMBLYAI.value:
            return AssemblyAISTTService(api_key=api_key)

        elif provider == ServiceProviders.GLADIA.value:
            return GladiaSTTService(api_key=api_key)

        elif provider == ServiceProviders.AZURE.value:
            return AzureSTTService(api_key=api_key)

        elif provider == ServiceProviders.GOOGLE_STT.value:
            return GoogleSTTService(api_key=api_key)

        elif provider == ServiceProviders.OPENAI_STT.value:
            return OpenAISTTService(api_key=api_key)

        else:
            raise ValueError(f"Unsupported STT provider: {stt_config.provider}")

    @staticmethod
    def create_tts_service(config: AgentConfig, audio_config: AudioConfig = AudioConfig()):
        tts_config = config.runtime_profiles.tts
        api_key = tts_config.api_key or os.getenv(f"{tts_config.provider.upper()}_API_KEY")

        if not api_key:
            raise ValueError(f"API Key missing for TTS provider: {tts_config.provider}")

        logger.info(
            f"Creating TTS service: provider={tts_config.provider}, voice={tts_config.voice_id}"
        )

        provider = tts_config.provider.lower()

        if provider == ServiceProviders.CARTESIA.value:
            lang_code = config.metadata.language or "en"
            try:
                lang = Language(lang_code)
            except Exception:
                lang = Language.EN

            return CartesiaTTSService(
                api_key=api_key,
                settings=CartesiaTTSService.Settings(
                    voice=tts_config.voice_id,
                    language=lang
                )
            )

        elif provider == ServiceProviders.ELEVENLABS.value:
            return ElevenLabsTTSService(
                api_key=api_key,
                voice_id=tts_config.voice_id,
                model_id=tts_config.model_id or "eleven_multilingual_v2",
            )

        elif provider == ServiceProviders.DEEPGRAM.value:
            return DeepgramTTSService(api_key=api_key, voice=tts_config.voice_id)

        elif provider == ServiceProviders.RIME.value:
            return RimeHttpTTSService(api_key=api_key, voice_id=tts_config.voice_id)

        elif provider == ServiceProviders.PLAYHT.value:
            # Note: PlayHTService has been removed from pipecat-ai core
            # If the user really needs it, they should use a custom service or we could
            # implement a basic one using aiohttp. For now, raising error.
            raise ValueError("PlayHTTTSService is currently not available in this Pipecat version.")

        elif provider == ServiceProviders.OPENAI.value or provider == "openai":
            return OpenAITTSService(api_key=api_key, voice=tts_config.voice_id)

        elif provider == ServiceProviders.AZURE.value:
            region = os.getenv("AZURE_REGION", "eastus")
            return AzureTTSService(
                api_key=api_key,
                region=region,
                voice=tts_config.voice_id,
            )

        elif provider == ServiceProviders.AWS.value:
            return AWSPollyTTSService(api_key=api_key, voice_id=tts_config.voice_id)

        elif provider == ServiceProviders.GOOGLE.value:
            return GoogleTTSService(api_key=api_key, voice=tts_config.voice_id)

        else:
            raise ValueError(f"Unsupported TTS provider: {tts_config.provider}")

    @staticmethod
    def create_llm_service(config: AgentConfig):
        llm_config = config.brain.llm
        api_key = llm_config.api_key or os.getenv(f"{llm_config.provider.upper()}_API_KEY")

        if not api_key:
            raise ValueError(f"API Key missing for LLM provider: {llm_config.provider}")

        logger.info(
            f"Creating LLM service: provider={llm_config.provider}, model={llm_config.model}"
        )

        temperature = llm_config.config.temperature if llm_config.config else 0.7
        system_instruction = llm_config.instructions or "You are a helpful voice assistant."

        provider = llm_config.provider.lower()

        if provider == ServiceProviders.OPENAI.value:
            return OpenAILLMService(
                api_key=api_key,
                settings=OpenAILLMService.Settings(model=llm_config.model, temperature=temperature),
            )

        elif provider == ServiceProviders.GROQ.value:
            return GroqLLMService(
                api_key=api_key,
                settings=GroqLLMService.Settings(model=llm_config.model, temperature=temperature),
            )

        elif provider == ServiceProviders.ANTHROPIC.value:
            return AnthropicLLMService(
                api_key=api_key,
                model=llm_config.model,
            )

        elif provider == ServiceProviders.GOOGLE.value:
            return GoogleLLMService(
                api_key=api_key,
                model=llm_config.model,
                settings=GoogleLLMService.Settings(
                    temperature=temperature,
                    system_instruction=system_instruction
                ),
            )

        elif provider == ServiceProviders.TOGETHER.value:
            return TogetherLLMService(
                api_key=api_key,
                model=llm_config.model,
            )

        elif provider == ServiceProviders.MISTRAL.value:
            return MistralLLMService(
                api_key=api_key,
                model=llm_config.model,
            )

        elif provider == ServiceProviders.ULTRAVOX.value:
            from pipecat.services.ultravox.llm import OneShotInputParams
            return UltravoxRealtimeLLMService(
                params=OneShotInputParams(
                    api_key=api_key,
                    model=llm_config.model,
                    system_prompt=system_instruction,
                    temperature=temperature,
                )
            )

        elif provider == "openrouter":
            return OpenAILLMService(
                api_key=api_key,
                base_url="https://openrouter.ai/api/v1",
                settings=OpenAILLMService.Settings(model=llm_config.model, temperature=temperature),
            )

        else:
            raise ValueError(f"Unsupported LLM provider: {llm_config.provider}")
