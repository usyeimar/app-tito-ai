"""
Task dispatcher — thin wrapper around arq to enqueue jobs from anywhere in the app.

Usage:
    from app.worker.dispatcher import dispatch

    await dispatch("start_campaign", campaign_id="abc-123")
"""

from arq import create_pool
from arq.connections import ArqRedis

from app.worker.config import get_redis_settings

_pool: ArqRedis | None = None


async def get_pool() -> ArqRedis:
    global _pool
    if _pool is None:
        _pool = await create_pool(get_redis_settings())
    return _pool


async def dispatch(task_name: str, **kwargs) -> str:
    """
    Enqueue a task by name and return the job ID.

    Args:
        task_name: Name of the registered arq function (e.g. "start_campaign").
        **kwargs: Arguments forwarded to the task function.

    Returns:
        job_id (str)
    """
    pool = await get_pool()
    job = await pool.enqueue_job(task_name, **kwargs)
    return job.job_id if job else ""
