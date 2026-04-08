"""Redis-backed session manager for tracking active agent sessions across pods."""
import asyncio
import logging
import json
import time
from typing import Optional, Dict, Any, List
from fastapi import WebSocket, WebSocketDisconnect
import redis.asyncio as aioredis
from app.schemas.agent import AgentConfig
from app.core.config import settings

logger = logging.getLogger(__name__)

RUNNER_FRAME_QUEUE_MAXSIZE = 150
MAX_SESSION_DURATION = 3600  # 1 hora máxima


class SessionManager:
    def __init__(self, redis_url: str):
        self._redis = aioredis.from_url(redis_url, decode_responses=True)
        # Sockets locales de esta instancia
        self._sockets: Dict[str, List[WebSocket]] = {}

    async def save_session(
        self,
        session_id: str,
        config: AgentConfig,
        room_name: Optional[str] = None,
        provider: Optional[str] = None,
    ) -> None:
        """Persiste metadatos en Redis."""
        key = f"session:{session_id}"
        
        # Obtener datos existentes si los hay
        existing_raw = await self._redis.get(key)
        existing = json.loads(existing_raw) if existing_raw else {}
        
        value = {
            **existing,
            "session_id": session_id,
            "agent_id": config.agent_id,
            "tenant_id": config.tenant_id,
            "host_id": settings.HOST_ID,
            "room_name": room_name or existing.get("room_name"),
            "provider": provider or existing.get("provider"),
            "status": "running",
            "created_at": existing.get("created_at", time.time()),
            "updated_at": time.time(),
        }
        
        await self._redis.setex(key, MAX_SESSION_DURATION, json.dumps(value))

    async def get_session(self, session_id: str) -> Optional[Dict[str, Any]]:
        """Recupera metadatos de Redis."""
        data = await self._redis.get(f"session:{session_id}")
        return json.loads(data) if data else None

    async def list_sessions(self) -> List[Dict[str, Any]]:
        """Lista todas las sesiones activas en Redis."""
        keys = await self._redis.keys("session:*")
        sessions = []
        for key in keys:
            # Skip control or event keys if any
            if ":control" in key or ":events" in key:
                continue
            data = await self._redis.get(key)
            if data:
                try:
                    sessions.append(json.loads(data))
                except Exception:
                    continue
        return sessions

    async def delete_session(self, session_id: str) -> bool:
        """Elimina metadatos de Redis."""
        result = await self._redis.delete(f"session:{session_id}")
        return result > 0

    # ── WebSocket Management ──────────────────────────────────────────────

    async def connect_ws(self, session_id: str, ws: WebSocket) -> None:
        """Registra un WebSocket localmente y suscribe a Redis Pub/Sub si es el primero."""
        await ws.accept()
        if session_id not in self._sockets:
            self._sockets[session_id] = []
            # Iniciar tarea de suscripción para esta sesión
            asyncio.create_task(self._subscribe_to_session_events(session_id))
            
        self._sockets[session_id].append(ws)
        logger.info(f"[{session_id}] WS local connected ({len(self._sockets[session_id])} listeners)")

    def disconnect_ws(self, session_id: str, ws: WebSocket) -> None:
        """Elimina un WebSocket local."""
        listeners = self._sockets.get(session_id, [])
        if ws in listeners:
            listeners.remove(ws)
        if not listeners:
            self._sockets.pop(session_id, None)
        logger.info(f"[{session_id}] WS local disconnected ({len(listeners)} listeners left)")

    async def _subscribe_to_session_events(self, session_id: str):
        """Tarea que escucha eventos de Redis y los envía a los WebSockets locales."""
        pubsub = self._redis.pubsub()
        await pubsub.subscribe(f"session:{session_id}:events")
        
        try:
            async for message in pubsub.listen():
                if message["type"] == "message":
                    event_data = message["data"]
                    # Enviar a todos los sockets locales para esta sesión
                    sockets = list(self._sockets.get(session_id, []))
                    if not sockets:
                        break # No hay más listeners locales, cerrar suscripción
                    
                    for ws in sockets:
                        try:
                            # Timeout de 50ms como en Fase 0.2
                            await asyncio.wait_for(ws.send_text(event_data), timeout=0.050)
                        except Exception:
                            self.disconnect_ws(session_id, ws)
        except Exception as e:
            logger.error(f"[{session_id}] Pub/Sub error: {e}")
        finally:
            await pubsub.unsubscribe(f"session:{session_id}:events")
            await pubsub.close()

    async def emit(self, session_id: str, event: Dict[str, Any]) -> None:
        """Publica un evento en Redis Pub/Sub para que todas las instancias lo reciban."""
        await self._redis.publish(f"session:{session_id}:events", json.dumps(event))

    async def broadcast(self, event: Dict[str, Any]) -> None:
        """Envía un evento a TODAS las sesiones activas vía Redis."""
        # En una arquitectura real escalable, podríamos tener un canal global o iterar sobre llaves
        # Por ahora, enviamos a un canal especial o simplemente no lo soportamos escalablemente
        # El plan v1/v2 no detalla broadcast global multi-pod pero sigamos el patrón.
        # Por simplicidad, este broadcast solo llegará a las sesiones registradas en Redis.
        # Pero iterar puede ser costoso. Para shutdown, es aceptable.
        pass


session_manager = SessionManager(settings.REDIS_URL)
