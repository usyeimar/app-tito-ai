import aiohttp
import logging
import os
import asyncio
import time
from typing import Dict, Any, Optional
from app.core.config import settings

logger = logging.getLogger(__name__)

class DailyService:
    """
    Servicio para interactuar con la API de Daily.co.
    Permite crear salas temporales y generar tokens de acceso para agentes y usuarios.
    """

    BASE_URL = "https://api.daily.co/v1"

    @staticmethod
    def _get_headers() -> Dict[str, str]:
        api_key = os.getenv("DAILY_API_KEY")
        return {
            "Authorization": f"Bearer {api_key}",
            "Content-Type": "application/json"
        }

    @staticmethod
    async def create_room(room_name: Optional[str] = None, expires_in: int = 3600) -> Dict[str, Any]:
        """
        Crea una sala en Daily.co.
        """
        url = f"{DailyService.BASE_URL}/rooms"
        # expiración en 1 hora por defecto
        exp = int(time.time() + expires_in)

        payload = {
            "properties": {
                "exp": exp,
                "eject_at_room_exp": True,
                "enable_chat": True
            }
        }
        if room_name:
            payload["name"] = room_name

        async with aiohttp.ClientSession() as session:
            async with session.post(url, json=payload, headers=DailyService._get_headers()) as resp:
                if resp.status not in (200, 201):
                    error_text = await resp.text()
                    logger.error(f"Error creating Daily room: {error_text}")
                    raise Exception(f"Daily API error: {error_text}")
                return await resp.json()

    @staticmethod
    async def create_token(room_name: str, is_owner: bool = False, participant_name: str = "Agent") -> str:
        """
        Genera un Meeting Token para una sala específica.
        """
        url = f"{DailyService.BASE_URL}/meeting-tokens"
        payload = {
            "properties": {
                "room_name": room_name,
                "is_owner": is_owner,
                "user_name": participant_name
            }
        }

        async with aiohttp.ClientSession() as session:
            async with session.post(url, json=payload, headers=DailyService._get_headers()) as resp:
                if resp.status != 200:
                    error_text = await resp.text()
                    logger.error(f"Error creating Daily token: {error_text}")
                    raise Exception(f"Daily API error: {error_text}")
                data = await resp.json()
                return data["token"]
        return None

    @staticmethod
    async def create_room_and_tokens(room_name: str, participant_name: str) -> Dict[str, Any]:
        """
        Helper para crear sala y ambos tokens (bot y usuario) de una vez.
        """
        room = await DailyService.create_room(room_name)
        room_url = room["url"]
        actual_room_name = room["name"]

        bot_token = await DailyService.create_token(actual_room_name, is_owner=True, participant_name="Tito-Agent")
        user_token = await DailyService.create_token(actual_room_name, is_owner=False, participant_name=participant_name)

        return {
            "ws_url": room_url,  # Daily usa la URL de la sala directamente
            "bot_token": bot_token,
            "user_token": user_token,
            "room_name": actual_room_name,
        }

    @staticmethod
    async def delete_room(room_name: str):
        """
        Elimina una sala en Daily.co.
        """
        url = f"{DailyService.BASE_URL}/rooms/{room_name}"
        async with aiohttp.ClientSession() as session:
            async with session.delete(url, headers=DailyService._get_headers()) as resp:
                if resp.status not in (200, 204):
                    logger.warning(f"Could not delete Daily room {room_name}: {await resp.text()}")
                else:
                    logger.info(f"Daily room {room_name} deleted successfully.")
