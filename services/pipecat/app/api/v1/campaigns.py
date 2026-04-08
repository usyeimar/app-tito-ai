import os
from typing import List

from fastapi import APIRouter, BackgroundTasks, Depends, HTTPException, Request

from app.api.schemas.campaign_schemas import CampaignCreateRequest, CampaignResponse
from app.api.schemas.error_schemas import APIErrorResponse
from app.dependencies import get_campaign_service
from app.domains.campaign.models.campaign import Campaign
from app.domains.campaign.services.campaign_service import CampaignService

router = APIRouter(tags=["Campaigns"])


def _map_to_response(campaign: Campaign, request: Request) -> CampaignResponse:
    """Helper to map Domain Entity to HATEOAS Response DTO."""
    base_url = str(request.base_url).rstrip("/")
    response = CampaignResponse.model_validate(campaign)

    # Add HATEOAS links
    response.add_link(rel="self", href=f"{base_url}/campaigns/{campaign.id}", method="GET")
    response.add_link(rel="start", href=f"{base_url}/campaigns/{campaign.id}/start", method="POST")

    return response


@router.post(
    "/campaigns",
    response_model=CampaignResponse,
    status_code=201,
    summary="Create a new campaign",
    description="Configures a new outbound campaign.",
    responses={422: {"model": APIErrorResponse}},
)
async def create_campaign(
    body: CampaignCreateRequest,
    request: Request,
    service: CampaignService = Depends(get_campaign_service),
):
    """
    Saves a new campaign configuration to the database.
    """
    # Convert DTO to Domain Entity
    campaign_entity = Campaign(**body.model_dump())

    created_campaign = service.create_campaign(campaign_entity)
    return _map_to_response(created_campaign, request)


@router.get(
    "/campaigns",
    response_model=List[CampaignResponse],
    summary="List all campaigns",
    description="Retrieve a list of all currently configured campaigns.",
)
async def list_campaigns(
    request: Request, service: CampaignService = Depends(get_campaign_service)
):
    """
    Returns a list of all campaigns.
    """
    campaigns = service.list_campaigns()
    return [_map_to_response(c, request) for c in campaigns]


@router.post(
    "/campaigns/{campaign_id}/start",
    summary="Start a campaign",
    description="Trigger the background process for an outbound campaign.",
    responses={404: {"model": APIErrorResponse}},
)
async def start_campaign(
    campaign_id: str,
    background_tasks: BackgroundTasks,
    service: CampaignService = Depends(get_campaign_service),
):
    """
    Locates a campaign by ID and starts its execution in the background.
    """
    try:
        await service.start_campaign_background(campaign_id)
        return {"message": f"Campaign {campaign_id} started in background"}
    except ValueError:
        raise HTTPException(status_code=404, detail="Campaign not found")


@router.get(
    "/campaigns/{campaign_id}",
    response_model=CampaignResponse,
    summary="Get campaign details",
    responses={404: {"model": APIErrorResponse}},
)
async def get_campaign(
    campaign_id: str, request: Request, service: CampaignService = Depends(get_campaign_service)
):
    """
    Fetch a single campaign's configuration by its ID.
    """
    campaign = service.get_campaign(campaign_id)
    if not campaign:
        raise HTTPException(status_code=404, detail="Campaign not found")
    return _map_to_response(campaign, request)
