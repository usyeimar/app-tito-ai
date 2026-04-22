"""Redis command consumer for async commands from Laravel.

Handles fire-and-forget commands only (e.g. session.terminate).
Session creation is handled via HTTP — see api/v1/sessions.py.

Protocol:
    1. Laravel LPUSHes a JSON command to `runner:commands` (or `runner:commands:{host_id}`).
    2. This consumer BRPOPs and executes the command. No response is sent back.
"""

import asyncio
import json
import logging
from typing import Optional

import redis.asyncio as aioredis

from app.core.config import settings
from app.services.session_manager import session_manager
from app.services.task_manager import task_manager

logger = logging.getLogger(__name__)

COMMANDS_KEY = "runner:commands"


class CommandConsumer:
    """Consumes async commands from Laravel via Redis."""

    def __init__(self) -> None:
        self._redis: Optional[aioredis.Redis] = None
        self._running = False
        self._task: Optional[asyncio.Task] = None

    @property
    def redis(self) -> aioredis.Redis:
        if self._redis is None:
            self._redis = aioredis.from_url(settings.REDIS_URL, decode_responses=True)
        return self._redis

    async def start(self) -> None:
        self._running = True
        self._task = asyncio.create_task(self._consume_loop(), name="command-consumer")
        logger.info(f"Command consumer started | keys={self._listen_keys()}")

    async def stop(self) -> None:
        self._running = False
        if self._task and not self._task.done():
            self._task.cancel()
            try:
                await self._task
            except asyncio.CancelledError:
                pass
        if self._redis:
            await self._redis.aclose()
            self._redis = None
        logger.info("Command consumer stopped")

    def _listen_keys(self) -> list[str]:
        keys = []
        if settings.HOST_ID:
            keys.append(f"{COMMANDS_KEY}:{settings.HOST_ID}")
        keys.append(COMMANDS_KEY)
        return keys

    async def _consume_loop(self) -> None:
        keys = self._listen_keys()

        while self._running:
            try:
                result = await self.redis.brpop(keys, timeout=5)
                if result is None:
                    continue

                _key, raw = result
                await self._handle_message(raw)

            except asyncio.CancelledError:
                break
            except aioredis.ConnectionError as e:
                logger.error(f"Redis connection lost: {e}, reconnecting in 2s...")
                self._redis = None
                await asyncio.sleep(2)
            except Exception as e:
                logger.error(f"Command consumer error: {e}", exc_info=True)
                await asyncio.sleep(1)

    async def _handle_message(self, raw: str) -> None:
        try:
            message = json.loads(raw)
        except json.JSONDecodeError:
            logger.warning(f"Invalid JSON command: {raw[:100]}")
            return

        command = message.get("command", "")
        payload = message.get("payload", {})

        logger.info(f"Command received | command={command}")

        try:
            match command:
                case "session.terminate":
                    await self._handle_terminate(payload)
                case _:
                    logger.warning(f"Unknown command: {command}")
        except Exception as e:
            logger.error(f"Command failed | command={command} | error={e}")

    async def _handle_terminate(self, payload: dict) -> None:
        session_id = payload.get("session_id", "")
        if not session_id:
            return

        session = await session_manager.get_session(session_id)
        if not session:
            logger.warning(f"Session not found for terminate: {session_id}")
            return

        await task_manager.stop(session_id)

        try:
            room_name = session.get("room_name")
            provider = session.get("provider", "livekit")
            if provider == "daily":
                from app.services.daily_service import DailyService
                await DailyService.delete_room(room_name)
            elif provider == "livekit":
                from app.services.livekit_service import LiveKitService
                await LiveKitService.delete_room(room_name)
        except Exception as e:
            logger.warning(f"Room cleanup failed: {session_id} | {e}")

        logger.info(f"Session terminated via Redis | session_id={session_id}")


command_consumer = CommandConsumer()
