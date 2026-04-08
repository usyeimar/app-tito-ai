"""
arq Worker definition.

Run with:
    arq app.worker.main.WorkerSettings

Or via the CLI helper:
    python cli.py worker
"""

from arq.connections import RedisSettings

from app.worker.config import get_redis_settings
from app.worker.tasks.campaign import start_campaign, stop_campaign


class WorkerSettings:
    """arq worker configuration."""

    functions = [start_campaign, stop_campaign]
    redis_settings: RedisSettings = get_redis_settings()

    # Optional: max concurrent jobs per worker
    max_jobs: int = 10

    # Optional: job timeout in seconds
    job_timeout: int = 3600
