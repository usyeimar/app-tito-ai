import time
from pydantic import BaseModel, Field, ConfigDict
from typing import Optional, List, Dict, Any


class SessionContext(BaseModel):
    """Metadatos de contexto de la sesion."""

    agent_id: str = Field(
        ..., description="ID del agente ejecutandose.", examples=["alloy-mvp-001"]
    )
    tenant_id: str = Field(
        ..., description="Organizacion propietaria.", examples=["tenant-abc-123"]
    )
    created_at: float = Field(
        default_factory=time.time, description="Timestamp Unix de creacion."
    )
    expires_at: Optional[float] = Field(
        None, description="Timestamp Unix de expiracion del token."
    )


class SessionLink(BaseModel):
    """Objeto de enlace para navegación HATEOAS en sesiones."""

    href: str = Field(..., description="URL del recurso.")
    method: str = Field(..., description="Método HTTP.")


class SessionResponse(BaseModel):
    """
    Respuesta de creacion de sesion con soporte HATEOAS.
    """

    session_id: str = Field(..., description="ID unico de la sesion.")
    room_name: str = Field(..., description="Nombre tecnico de la sala WebRTC.")
    provider: str = Field(..., description="Proveedor de transporte WebRTC.")
    ws_url: Optional[str] = Field(
        None, description="URL WebSocket (wss://) para conectar al room."
    )
    playground_url: Optional[str] = Field(
        None, description="URL del LiveKit Playground para probar el bot."
    )
    access_token: Optional[str] = Field(None, description="Token JWT para unirse.")
    context: SessionContext
    links: Dict[str, SessionLink] = Field(default_factory=dict, alias="_links")

    model_config = ConfigDict(
        populate_by_name=True,
        json_schema_extra={
            "example": {
                "session_id": "sess_a1b2c3d4e5f6",
                "room_name": "tito_agent-001_f7e8d9c0",
                "provider": "daily",
                "url": "https://your-domain.daily.co/tito_agent-001_f7e8d9c0",
                "access_token": "eyJhbGciOiJIUzI1NiIs...",
                "context": {
                    "agent_id": "alloy-mvp-001",
                    "tenant_id": "tenant-abc-123",
                    "created_at": 1712444800.0,
                    "expires_at": 1712448400.0,
                },
                "_links": {
                    "self": {
                        "href": "/api/v1/sessions/sess_a1b2c3d4e5f6",
                        "method": "GET",
                    },
                    "stop": {
                        "href": "/api/v1/sessions/sess_a1b2c3d4e5f6",
                        "method": "DELETE",
                    },
                    "ws": {
                        "href": "ws://host/api/v1/sessions/sess_a1b2c3d4e5f6/ws",
                        "method": "GET",
                    },
                },
            }
        },
    )


class SessionListResponse(BaseModel):
    """Lista de sesiones activas con soporte HATEOAS."""

    sessions: List[Dict[str, Any]] = Field(default_factory=list)
    count: int = Field(..., description="Numero total de sesiones activas.")
    status: str = Field(..., description="Estado operativo del runner.")
    links: Dict[str, SessionLink] = Field(default_factory=dict, alias="_links")

    model_config = ConfigDict(
        populate_by_name=True,
        json_schema_extra={
            "example": {
                "sessions": [],
                "count": 0,
                "status": "OPERATIONAL",
                "_links": {
                    "self": {"href": "/api/v1/sessions", "method": "GET"},
                    "create": {"href": "/api/v1/sessions", "method": "POST"},
                },
            }
        },
    )


class ActionResponse(BaseModel):
    """Respuesta generica de accion exitosa."""

    success: bool = Field(
        ..., description="Indica si la operacion fue exitosa.", examples=[True]
    )
    message: str = Field(
        ...,
        description="Mensaje descriptivo del resultado.",
        examples=["Sesion sess_a1b2c3d4e5f6 terminada exitosamente."],
    )

    model_config = ConfigDict(
        json_schema_extra={
            "example": {
                "success": True,
                "message": "Sesion sess_a1b2c3d4e5f6 terminada exitosamente.",
            }
        }
    )
