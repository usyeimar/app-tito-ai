from typing import Any, Dict, Optional

from fastapi import APIRouter, Depends, HTTPException, Request
from fastapi.responses import JSONResponse
from loguru import logger
from pydantic import BaseModel

from app.api.schemas.error_schemas import APIErrorResponse
from app.api.schemas.schemas import CallRequest, CallResponse, Link
from app.dependencies import get_call_service
from app.domains.call.models.call import CallConfig
from app.domains.call.services.call_service import CallService

router = APIRouter(tags=["Calls"])


class ConnectRequest(BaseModel):
    variables: Optional[Dict[str, Any]] = None


@router.post(
    "/calls",
    response_model=CallResponse,
    status_code=201,
    summary="Create a new call",
    description="Spawns a new agent process for a specific assistant.",
    responses={404: {"model": APIErrorResponse}, 422: {"model": APIErrorResponse}},
)
async def create_call(
    request: Request,
    body: CallRequest,
    service: CallService = Depends(get_call_service),
):
    """
    Initiates a new call by starting a bot process with the specified assistant configuration.
    """
    # Map DTO to Domain Model
    config = CallConfig(
        assistant_id=body.assistant_id,
        phone_number=body.phone_number,
        variables=body.variables,
        dynamic_vocabulary=body.dynamic_vocabulary,
        secrets=body.secrets,
    )

    try:
        session = await service.initiate_call(config)
    except ValueError as e:
        raise HTTPException(status_code=404, detail=str(e))
    except Exception as e:
        logger.error(f"Call failed: {e}")
        raise HTTPException(status_code=500, detail=str(e))

    base_url = str(request.base_url).rstrip("/")
    return CallResponse(
        id=session.id,
        status=session.status,
        room_url=session.room_url,
        token=session.token,
        ice_config=session.ice_config,
        _links=[
            Link(href=f"{base_url}/status/{session.id}", method="GET", rel="status"),
            Link(
                href=f"{base_url}/assistants/{body.assistant_id}",
                method="GET",
                rel="assistant",
            ),
        ],
    )


@router.post(
    "/connect",
    summary="RTVI connection (dynamic)",
    description="API-friendly endpoint returning connection credentials for RTVI clients.",
    responses={422: {"model": APIErrorResponse}},
)
async def rtvi_connect(
    request: Request, service: CallService = Depends(get_call_service)
) -> Dict[str, Any]:
    """
    Dynamic connection endpoint that accepts inline configuration for the bot.
    """
    try:
        body = await request.json()
    except Exception:
        body = {}

    session = await service.start_rtvi_session(body)

    return {
        "room_url": session.room_url,
        "token": session.token,
        "ice_config": session.ice_config,
        "bot_pid": int(session.id),
        "status_endpoint": f"/status/{session.id}",
    }


@router.post(
    "/connect/{assistant_id}",
    summary="RTVI connection with assistant",
    description="Start a bot using a pre-defined assistant configuration for RTVI.",
    responses={404: {"model": APIErrorResponse}, 422: {"model": APIErrorResponse}},
)
async def connect_assistant(
    assistant_id: str,
    request: Request,
    body: Optional[ConnectRequest] = None,
    service: CallService = Depends(get_call_service),
):
    """
    Connects an RTVI client to a bot process based on an assistant ID.
    """
    variables = body.variables if body else None
    config = CallConfig(assistant_id=assistant_id, variables=variables)

    try:
        session = await service.initiate_call(config)
    except ValueError as e:
        raise HTTPException(status_code=404, detail=str(e))

    return {
        "room_url": session.room_url,
        "token": session.token,
        "ice_config": session.ice_config,
        "bot_pid": int(session.id),
        "status_endpoint": f"/status/{session.id}",
    }


@router.get(
    "/status/{pid}",
    summary="Get process status",
    responses={404: {"model": APIErrorResponse}},
)
def get_status(pid: str, service: CallService = Depends(get_call_service)):
    """
    Check if a bot process is still running or has finished.
    """
    try:
        status = service.get_call_status(pid)
        return JSONResponse({"bot_id": pid, "status": status})
    except Exception:
        raise HTTPException(status_code=404, detail=f"Bot with PID {pid} not found")


@router.delete(
    "/calls/{pid}",
    summary="Stop a running call process",
    responses={404: {"model": APIErrorResponse}},
)
async def stop_call(pid: str, service: CallService = Depends(get_call_service)):
    """
    Stops a running bot process and cleans up its room.
    """
    try:
        success = await service.end_call(pid)
        if success:
            return JSONResponse({"message": f"Call {pid} stopped successfully"})
        else:
            return JSONResponse({"message": f"Call {pid} already stopped or not found"})
    except HTTPException as e:
        raise e
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
