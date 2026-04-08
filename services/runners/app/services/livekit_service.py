import os
import uuid
import logging
from typing import Dict, Any
from livekit import api

from app.core.config import settings

logger = logging.getLogger(__name__)


class LiveKitService:
    """Servicio para interactuar con la API de LiveKit."""

    @staticmethod
    def create_room_and_tokens(room_name: str, participant_name: str) -> Dict[str, Any]:
        """
        Genera los tokens de acceso para el cliente web y para el bot.
        """
        logger.info(f"Generando tokens de LiveKit para la sala {room_name}")

        # Cliente HTTP o cliente API de LiveKit para generar tokens
        # livekit-api tiene métodos para esto
        try:
            # Token para el Bot
            bot_grant = api.VideoGrants(room=room_name, room_join=True)
            bot_token = (
                api.AccessToken(settings.LIVEKIT_API_KEY, settings.LIVEKIT_API_SECRET)
                .with_grants(bot_grant)
                .with_identity("Agent-Bot")
                .to_jwt()
            )

            # Token para el Usuario (Frontend Web/Mobile)
            user_grant = api.VideoGrants(room=room_name, room_join=True)
            user_token = (
                api.AccessToken(settings.LIVEKIT_API_KEY, settings.LIVEKIT_API_SECRET)
                .with_grants(user_grant)
                .with_identity(participant_name)
                .to_jwt()
            )

            return {
                "ws_url": settings.LIVEKIT_URL,
                "bot_token": bot_token,
                "user_token": user_token,
                "room_name": room_name,
            }
        except Exception as e:
            logger.error(f"Error generando tokens de LiveKit: {e}")
            raise

    @staticmethod
    async def delete_room(room_name: str):
        """
        Elimina una sala de LiveKit, desconectando a todos los participantes.
        """
        try:
            from livekit import api as lk_api

            lk_client = lk_api.LiveKitAPI(
                settings.LIVEKIT_URL,
                settings.LIVEKIT_API_KEY,
                settings.LIVEKIT_API_SECRET,
            )
            await lk_client.room.delete_room(lk_api.DeleteRoomRequest(room=room_name))
            await lk_client.aclose()
            logger.info(f"Sala {room_name} eliminada exitosamente.")
        except Exception as e:
            logger.error(f"Error eliminando sala {room_name}: {e}")
            raise
