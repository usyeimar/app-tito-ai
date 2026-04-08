from typing import Any, Dict, List, Optional

from fastapi import APIRouter, Depends, HTTPException, Request
from fastapi.responses import JSONResponse
from pydantic import BaseModel, Field

from app.api.responses.hateoas import HateoasModel, Link
from app.dependencies import get_assistant_service
from app.domains.assistant.models.assistant import Assistant
from app.domains.assistant.services.assistant_service import AssistantService

router = APIRouter(tags=["Assistants"])

# --- DTOs ---


class ChatRequest(BaseModel):
    message: str


class ChatResponse(BaseModel):
    response: str


class AssistantResponseDTO(HateoasModel):
    id: str
    version: str
    metadata: Dict[str, Any]
    architecture: Dict[str, Any]
    agent: Dict[str, Any]
    io_layer: Dict[str, Any]
    pipeline_settings: Dict[str, Any]
    capabilities: Dict[str, Any]
    observability: Dict[str, Any]
    compliance: Dict[str, Any]
    created_at: str

    links: List[Link] = Field(default_factory=list, alias="_links")

    class Config:
        from_attributes = True


def _map_to_response(assistant: Assistant, request: Request) -> AssistantResponseDTO:
    data = assistant.model_dump(mode="json")
    data["created_at"] = str(data["created_at"])

    dto = AssistantResponseDTO(**data)
    base = str(request.base_url).rstrip("/")
    dto.add_link("self", f"{base}/assistants/{assistant.id}", "GET")
    dto.add_link("chat", f"{base}/assistants/{assistant.id}/chat", "POST")
    dto.add_link(
        "voice_ws", f"{base.replace('http', 'ws')}/assistants/{assistant.id}/voice", "WEBSOCKET"
    )
    return dto


# --- Endpoints ---


@router.get("/assistants", response_model=List[AssistantResponseDTO])
async def list_assistants(
    request: Request, service: AssistantService = Depends(get_assistant_service)
):
    assistants = service.list_assistants()
    return [_map_to_response(a, request) for a in assistants]


@router.post("/assistants", response_model=AssistantResponseDTO, status_code=201)
async def create_assistant(
    assistant: Assistant,
    request: Request,
    service: AssistantService = Depends(get_assistant_service),
):
    created = service.create_assistant(assistant)
    return _map_to_response(created, request)


@router.get("/assistants/{assistant_id}", response_model=AssistantResponseDTO)
async def get_assistant(
    assistant_id: str, request: Request, service: AssistantService = Depends(get_assistant_service)
):
    assistant = service.get_assistant(assistant_id)
    if not assistant:
        raise HTTPException(status_code=404, detail="Assistant not found")
    return _map_to_response(assistant, request)


@router.put("/assistants/{assistant_id}", response_model=AssistantResponseDTO)
async def update_assistant(
    assistant_id: str,
    update_data: dict,
    request: Request,
    service: AssistantService = Depends(get_assistant_service),
):
    updated = service.update_assistant(assistant_id, update_data)
    if not updated:
        raise HTTPException(status_code=404, detail="Assistant not found")
    return _map_to_response(updated, request)


@router.delete("/assistants/{assistant_id}")
async def delete_assistant(
    assistant_id: str, service: AssistantService = Depends(get_assistant_service)
):
    success = service.delete_assistant(assistant_id)
    if not success:
        raise HTTPException(status_code=404, detail="Assistant not found")
    return JSONResponse({"message": "Deleted successfully"})


@router.post("/assistants/{assistant_id}/chat", response_model=ChatResponse)
async def chat_with_assistant(
    assistant_id: str, body: ChatRequest, service: AssistantService = Depends(get_assistant_service)
):
    try:
        response_text = await service.chat_with_assistant(assistant_id, body.message)
        return ChatResponse(response=response_text)
    except ValueError as e:
        raise HTTPException(status_code=404, detail=str(e))
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
