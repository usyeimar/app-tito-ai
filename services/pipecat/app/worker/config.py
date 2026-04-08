"""
Redis / arq worker settings.
Configure REDIS_URL via environment variable.
"""

import os

from arq.connections import RedisSettings

REDIS_URL = os.getenv("REDIS_URL", "redis://localhost:6379")


def get_redis_settings() -> RedisSettings:
    return RedisSettings.from_dsn(REDIS_URL)
