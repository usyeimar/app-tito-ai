"""Server configuration management module."""

import os
from typing import Any, Dict, List, Optional

from dotenv import load_dotenv


class ServerConfig:
    def __init__(self):
        load_dotenv()

        # Server settings
        self.host: str = os.getenv("HOST", "0.0.0.0")
        self.port: int = int(os.getenv("FAST_API_PORT", "7860"))
        self.reload: bool = os.getenv("RELOAD", "false").lower() == "true"

        # Daily API settings
        self.daily_api_key: str = os.getenv("DAILY_API_KEY")
        self.daily_api_url: str = os.getenv("DAILY_API_URL", "https://api.daily.co/v1")

        # Bot settings
        self.max_bots_per_room: int = int(os.getenv("MAX_BOTS_PER_ROOM", "1"))

        # WebRTC / ICE settings
        self.ice_domain: str = os.getenv("CLOUDFLARE_TURN_DOMAIN", "turn.cloudflare.com")
        self.ice_key_id: Optional[str] = os.getenv("CLOUDFLARE_TURN_KEY_ID")
        self.ice_key_secret: Optional[str] = os.getenv("CLOUDFLARE_TURN_KEY_SECRET")

        # Validate required settings
        if not self.daily_api_key:
            raise ValueError("DAILY_API_KEY environment variable must be set")

    def get_ice_config(self) -> Dict[str, Any]:
        """Returns the WebRTC ICE configuration for clients."""
        if not self.ice_key_id or not self.ice_key_secret:
            return {"iceServers": [{"urls": ["stun:stun.cloudflare.com:3478"]}]}

        return {
            "iceServers": [
                {
                    "urls": [
                        "stun:stun.cloudflare.com:3478",
                        f"turn:{self.ice_domain}:3478?transport=udp",
                        f"turn:{self.ice_domain}:3478?transport=tcp",
                        f"turns:{self.ice_domain}:5349?transport=tcp",
                    ],
                    "username": self.ice_key_id,
                    "credential": self.ice_key_secret,
                }
            ]
        }
