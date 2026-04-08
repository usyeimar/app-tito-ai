"""Bot session manager — runs each Pipecat bot as an asyncio Task (in-process)."""

import asyncio
import json
import random
from typing import Dict, List, Optional, Tuple

from fastapi import HTTPException
from loguru import logger

from app.core.config.server import ServerConfig
from app.domains.call.interfaces.bot_process_manager import BotProcessManager
from app.domains.call.interfaces.room_provider import RoomProvider


class LocalBotSessionManager(BotProcessManager):
    """Runs each bot as an asyncio Task instead of a subprocess."""

    def __init__(self, room_provider: RoomProvider):
        # session_id -> (asyncio.Task, room_url)
        self.active_sessions: Dict[int, Tuple[asyncio.Task, str]] = {}
        self._next_id = 1
        self.config = ServerConfig()
        self.room_provider = room_provider

    # ------------------------------------------------------------------
    # BotProcessManager interface
    # ------------------------------------------------------------------

    async def start_bot(
        self,
        room_url: str,
        token: str,
        args: List[str],
        env_vars: Optional[Dict[str, str]] = None,
    ) -> int:
        active_in_room = sum(
            1 for task, url in self.active_sessions.values() if url == room_url and not task.done()
        )
        if active_in_room >= self.config.max_bots_per_room:
            raise HTTPException(status_code=429, detail="Room capacity reached")

        session_id = self._next_id
        self._next_id += 1

        task = asyncio.create_task(
            self._run_bot_session(room_url, token, args, env_vars or {}),
            name=f"bot-session-{session_id}",
        )
        self.active_sessions[session_id] = (task, room_url)

        def _on_done(t: asyncio.Task):
            exc = t.exception() if not t.cancelled() else None
            if exc:
                logger.error(f"Bot session {session_id} failed: {exc}")

        task.add_done_callback(_on_done)
        logger.info(f"Started bot session {session_id} for room {room_url}")
        return session_id

    def get_status(self, pid: int) -> str:
        if pid not in self.active_sessions:
            raise HTTPException(status_code=404, detail="Bot session not found")
        task, _ = self.active_sessions[pid]
        return "finished" if task.done() else "running"

    async def cleanup(self):
        """Periodic cleanup of finished sessions."""
        while True:
            try:
                for sid in list(self.active_sessions.keys()):
                    task, room_url = self.active_sessions[sid]
                    if task.done():
                        logger.info(f"🧹 Cleaning up session {sid} for room {room_url}")
                        await self.room_provider.delete_room(room_url)
                        del self.active_sessions[sid]
            except Exception as e:
                logger.error(f"Cleanup error: {e}")
            await asyncio.sleep(5)

    # ------------------------------------------------------------------
    # Internal: build BotConfig from CLI args and run the pipeline
    # ------------------------------------------------------------------

    async def _run_bot_session(
        self,
        room_url: str,
        token: str,
        args: List[str],
        env_vars: Dict[str, str],
    ) -> None:
        """Apply env overrides, resolve the assistant, and run the bot."""
        import os

        # Apply caller-supplied env vars (e.g. secrets)
        original: Dict[str, Optional[str]] = {}
        for k, v in env_vars.items():
            original[k] = os.environ.get(k)
            os.environ[k] = v

        try:
            await self._execute_bot(room_url, token, args)
        finally:
            for k, orig in original.items():
                if orig is None:
                    os.environ.pop(k, None)
                else:
                    os.environ[k] = orig

    async def _execute_bot(self, room_url: str, token: str, args: List[str]) -> None:
        """Parse args, build config, and run the bot pipeline in-process."""
        import argparse
        import os

        parser = argparse.ArgumentParser(add_help=False)
        parser.add_argument("-u", "--room-url")
        parser.add_argument("-t", "--token")
        parser.add_argument("-a", "--architecture-type", type=str.lower)
        parser.add_argument("-n", "--bot-name")
        parser.add_argument("-l", "--llm-provider", type=str.lower)
        parser.add_argument("-m", "--llm-model")
        parser.add_argument("-T", "--llm-temperature", type=float)
        parser.add_argument("-s", "--stt-provider", type=str.lower)
        parser.add_argument("-p", "--tts-provider", type=str.lower)
        parser.add_argument("-v", "--tts-voice")
        parser.add_argument(
            "--enable-stt-mute-filter",
            type=lambda x: str(x).lower() in ("true", "1", "yes"),
        )
        parser.add_argument("--stt-keywords")
        parser.add_argument("--assistant-id")
        parser.add_argument("--agent-type", type=str.lower)
        parser.add_argument(
            "--speak-first",
            type=lambda x: str(x).lower() in ("true", "1", "yes"),
        )
        parser.add_argument("--prompt-variables")
        parser.add_argument("--system-prompt")
        parsed, _ = parser.parse_known_args(args)

        # ------------------------------------------------------------------
        # 1. Load assistant from storage (preferred path)
        # ------------------------------------------------------------------
        loaded_assistant = None
        if parsed.assistant_id:
            loaded_assistant = self._load_assistant(parsed.assistant_id)

        # ------------------------------------------------------------------
        # 2. Apply env overrides from CLI args (override assistant defaults)
        # ------------------------------------------------------------------
        if parsed.architecture_type:
            os.environ["ARCHITECTURE_TYPE"] = parsed.architecture_type
        if parsed.bot_name:
            os.environ["BOT_NAME"] = parsed.bot_name
        if parsed.llm_provider:
            os.environ["LLM_PROVIDER"] = parsed.llm_provider
        if parsed.llm_model:
            os.environ["LLM_MODEL"] = parsed.llm_model
        if parsed.llm_temperature is not None:
            os.environ["LLM_TEMPERATURE"] = str(parsed.llm_temperature)
        if parsed.stt_provider:
            os.environ["STT_PROVIDER"] = parsed.stt_provider
        if parsed.tts_provider:
            os.environ["TTS_PROVIDER"] = parsed.tts_provider
        if parsed.tts_voice:
            os.environ["TTS_VOICE"] = parsed.tts_voice
        if parsed.enable_stt_mute_filter is not None:
            os.environ["ENABLE_STT_MUTE_FILTER"] = str(parsed.enable_stt_mute_filter).lower()
        if parsed.stt_keywords:
            os.environ["STT_KEYWORDS"] = parsed.stt_keywords
        if parsed.agent_type:
            os.environ["AGENT_TYPE"] = parsed.agent_type
        if parsed.speak_first is not None:
            os.environ["SPEAK_FIRST"] = "true" if parsed.speak_first else "false"

        # ------------------------------------------------------------------
        # 3. Build BotConfig (reads env vars set above)
        # ------------------------------------------------------------------
        from app.core.config.bot import BotConfig

        config = BotConfig()

        # ------------------------------------------------------------------
        # 4. Hydrate config from the loaded assistant (v3 schema)
        # ------------------------------------------------------------------
        if loaded_assistant:
            self._hydrate_config_from_assistant(config, loaded_assistant)

        # ------------------------------------------------------------------
        # 5. Determine bot class
        # ------------------------------------------------------------------
        arch = config.architecture_type
        if arch == "flow":
            from app.domains.agent.bots.flow import FlowBot

            bot_class = FlowBot
        elif arch == "multimodal":
            from app.domains.agent.bots.multimodal import MultimodalBot

            bot_class = MultimodalBot
        else:
            from app.domains.agent.bots.simple import SimpleBot

            bot_class = SimpleBot

        # ------------------------------------------------------------------
        # 6. Build system messages
        # ------------------------------------------------------------------
        system_messages = None
        base_prompt = parsed.system_prompt

        if not base_prompt and loaded_assistant:
            base_prompt = loaded_assistant.agent.system_prompt

        if parsed.prompt_variables and base_prompt:
            try:
                variables = json.loads(parsed.prompt_variables)
                base_prompt = base_prompt.format(**variables)
            except Exception as e:
                logger.warning(f"Failed to apply prompt variables: {e}")

        if base_prompt:
            system_messages = [{"role": "system", "content": base_prompt}]

        # ------------------------------------------------------------------
        # 7. Webhook config
        # ------------------------------------------------------------------
        webhook_config = None
        if loaded_assistant and loaded_assistant.webhooks:
            webhook_config = loaded_assistant.webhooks

        # ------------------------------------------------------------------
        # 8. Run the bot pipeline
        # ------------------------------------------------------------------
        from runners.webrtc_runner import run_bot

        await run_bot(
            bot_class,
            config,
            room_url=room_url,
            token=token,
            system_messages=system_messages,
            webhook_config=webhook_config,
        )

    # ------------------------------------------------------------------
    # Helpers
    # ------------------------------------------------------------------

    @staticmethod
    def _load_assistant(assistant_id: str):
        """Load an Assistant from the file repository."""
        import os

        from app.domains.assistant.services.assistant_service import AssistantService
        from app.infrastructure.repositories.file_assistant_repository import (
            FileAssistantRepository,
        )

        backend_root = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
        data_dir = os.path.join(backend_root, "resources", "data", "assistants")
        repo = FileAssistantRepository(data_dir)
        service = AssistantService(repo)
        assistant = service.get_assistant(assistant_id)
        if not assistant:
            logger.error(f"Assistant {assistant_id} not found")
        return assistant

    @staticmethod
    def _hydrate_config_from_assistant(config, assistant) -> None:
        """
        Push all relevant fields from the v3 Assistant model into BotConfig
        (which still reads from env vars / direct attributes).
        """
        import os

        # Architecture
        os.environ["ARCHITECTURE_TYPE"] = assistant.architecture_type

        # Bot name
        os.environ["BOT_NAME"] = assistant.name

        # LLM
        os.environ["LLM_PROVIDER"] = assistant.agent.provider
        if assistant.agent.model:
            os.environ["LLM_MODEL"] = assistant.agent.model
        os.environ["LLM_TEMPERATURE"] = str(assistant.agent.temperature)

        # STT
        if assistant.io_layer.stt:
            os.environ["STT_PROVIDER"] = assistant.io_layer.stt.provider
            if assistant.io_layer.stt.model:
                os.environ["STT_MODEL"] = assistant.io_layer.stt.model
            if assistant.io_layer.stt.language:
                os.environ["STT_LANGUAGE"] = assistant.io_layer.stt.language
            if assistant.io_layer.stt.enable_mute_filter:
                os.environ["ENABLE_STT_MUTE_FILTER"] = "true"

        # TTS
        if assistant.io_layer.tts:
            os.environ["TTS_PROVIDER"] = assistant.io_layer.tts.provider
            if assistant.io_layer.tts.voice_id:
                os.environ["TTS_VOICE"] = assistant.io_layer.tts.voice_id
            if assistant.io_layer.tts.language:
                os.environ["TTS_LANGUAGE"] = assistant.io_layer.tts.language

        # Speak first
        os.environ["SPEAK_FIRST"] = "true" if assistant.pipeline_settings.speak_first else "false"

        # Tools (from capabilities)
        active_tools = [t.model_dump() for t in assistant.capabilities.tools if not t.disabled]
        config.tools = active_tools

        # Flow config
        config.flow_config = assistant.flow

        # Inactivity messages (v3 steps → BaseBot format)
        config.inactivity_messages = assistant.pipeline_settings.inactivity_messages

        # Initial message / delay
        config.initial_message = assistant.pipeline_settings.initial_message
        config.initial_delay = assistant.pipeline_settings.initial_delay
        config.initial_message_interruptible = (
            assistant.pipeline_settings.initial_message_interruptible
        )
        config.interruptibility = assistant.pipeline_settings.interruptibility
