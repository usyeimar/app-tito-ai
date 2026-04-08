"""
Campaign-related async tasks dispatched via Redis (arq).
"""

import os

from arq import ArqRedis
from loguru import logger

from app.dependencies import get_campaign_service


async def start_campaign(ctx: dict, campaign_id: str) -> dict:
    """
    Enqueue a campaign to start its outbound dialing loop.

    Args:
        ctx: arq context (contains redis connection, etc.)
        campaign_id: ID of the campaign to start.
    """
    logger.info(f"[task] start_campaign → {campaign_id}")
    service = get_campaign_service()
    await service.start_campaign_background(campaign_id)
    return {"campaign_id": campaign_id, "status": "started"}


async def stop_campaign(ctx: dict, campaign_id: str) -> dict:
    """
    Signal a running campaign to stop.
    """
    logger.info(f"[task] stop_campaign → {campaign_id}")
    service = get_campaign_service()
    campaign = service.get_campaign(campaign_id)
    if campaign:
        campaign.status = "paused"
        service.repository.save(campaign)
    return {"campaign_id": campaign_id, "status": "paused"}
