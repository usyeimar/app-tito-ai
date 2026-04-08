import logging
import asyncio
import aiohttp
import time
from typing import Dict, Any, Optional
from app.core.config import settings

logger = logging.getLogger(__name__)


class WebhookService:
    """
    Servicio para notificar eventos del Runner al backend de Laravel.
    """

    @staticmethod
    async def emit_event_async(
        tenant_id: str,
        agent_id: str,
        event_type: str,
        room_name: str,
        data: Optional[Dict[str, Any]] = None,
        override_url: Optional[str] = None
    ):
        """
        Versión asíncrona (fire-and-forget) de emit_event.
        No bloquea el pipeline.
        """
        asyncio.create_task(
            WebhookService.emit_event(
                tenant_id, agent_id, event_type, room_name, data, override_url
            )
        )

    @staticmethod
    async def emit_event(
        tenant_id: str,
        agent_id: str,
        event_type: str,
        room_name: str,
        data: Optional[Dict[str, Any]] = None,
        override_url: Optional[str] = None
    ):
        """
        Envía un evento al webhook de Laravel.
        Usa override_url (completa) si está presente, de lo contrario construye la URL por defecto.
        """
        if override_url:
            url = override_url
        else:
            logger.warning("⚠️ No hay URL de reporte configurada.")
            return

        payload = {
            "event": event_type,
            "agent_id": agent_id,
            "tenant_id": tenant_id,
            "room_name": room_name,
            "timestamp": time.time(),
            "data": data or {}
        }

        headers = {
            "Content-Type": "application/json",
            "X-Tito-Agent-Key": settings.BACKEND_API_KEY
        }

        # Configurar un timeout corto para no bloquear al agente
        timeout = aiohttp.ClientTimeout(total=5)

        try:
            async with aiohttp.ClientSession(timeout=timeout) as session:
                async with session.post(url, json=payload, headers=headers) as response:
                    if response.status >= 400:
                        logger.warning(f"⚠️ Laravel respondió con error ({response.status}) en evento {event_type}")
                    else:
                        logger.debug(f"✅ Evento '{event_type}' enviado a Laravel")
        except Exception as e:
            # Capturamos el error pero permitimos que el bot continúe
            logger.error(f"❌ Error de red con Laravel (Webhooks): {str(e)}")
