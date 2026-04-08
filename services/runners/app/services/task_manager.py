import asyncio
import logging
from typing import Dict, Optional

logger = logging.getLogger(__name__)

class TaskManager:
    def __init__(self):
        self._tasks: Dict[str, asyncio.Task] = {}
        self._lock = asyncio.Lock()

    async def add(self, session_id: str, task: asyncio.Task) -> None:
        async with self._lock:
            if session_id in self._tasks:
                raise ValueError(f"Session {session_id} already has a running task")
            self._tasks[session_id] = task

    async def stop(self, session_id: str) -> None:
        async with self._lock:
            task = self._tasks.pop(session_id, None)
        if task is None:
            logger.warning("stop_unknown_session", extra={"session_id": session_id})
            return
        task.cancel()
        try:
            await task  # esperar que el finally del pipeline se ejecute
        except asyncio.CancelledError:
            pass
        except Exception as e:
            logger.error(f"Error stopping task for session {session_id}: {e}")

    async def remove(self, session_id: str) -> None:
        """Llamado por el pipeline al terminar para hacer auto-cleanup."""
        async with self._lock:
            self._tasks.pop(session_id, None)

    def count(self) -> int:
        return len(self._tasks)

    async def stop_all(self) -> None:
        async with self._lock:
            session_ids = list(self._tasks.keys())
        await asyncio.gather(*[self.stop(sid) for sid in session_ids], return_exceptions=True)

    def get_task(self, session_id: str) -> Optional[asyncio.Task]:
        return self._tasks.get(session_id)

# Singleton global
task_manager = TaskManager()
