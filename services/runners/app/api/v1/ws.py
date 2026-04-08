"""
Compatibility shim — registry.broadcast now delegates to session_manager.
The real WebSocket endpoint lives at /sessions/{session_id}/ws (sessions.py).
"""
from app.services.session_manager import session_manager


class _RegistryShim:
    async def broadcast(self, message: dict) -> None:
        await session_manager.broadcast(message)


registry = _RegistryShim()
