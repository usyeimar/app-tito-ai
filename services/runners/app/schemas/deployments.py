from typing import Optional, Literal, Dict
from pydantic import BaseModel, Field

class DeploymentLink(BaseModel):
    """Objeto de enlace para navegación HATEOAS."""
    href: str = Field(..., description="URL del recurso.")
    method: str = Field(..., description="Método HTTP para la acción.")

class SIPProvisionRequest(BaseModel):
    """Solicitud para activar un canal SIP para un agente."""
    agent_id: str = Field(..., description="ID único del agente.", examples=["agent-001"])
    workspace_slug: str = Field(..., description="Slug del tenant.", examples=["alloy-finance"])
    sip_provider: Literal["livekit", "daily"] = Field("livekit", description="Proveedor SIP.")
    api_key: Optional[str] = Field(None, description="API Key opcional.")

class SIPProvisionResponse(BaseModel):
    """Respuesta con las credenciales SIP del agente y enlaces HATEOAS."""
    agent_id: str
    sip_uri: str
    sip_username: str
    sip_password: str
    status: str = Field("ACTIVE")
    workspace_subdomain: str
    links: Dict[str, DeploymentLink] = Field(default_factory=dict, alias="_links")

    model_config = {
        "populate_by_name": True
    }

class SIPRotateKeyResponse(BaseModel):
    """Respuesta al rotar la clave SIP con navegación."""
    agent_id: str
    new_api_key: str
    status: str = "UPDATED"
    links: Dict[str, DeploymentLink] = Field(default_factory=dict, alias="_links")

    model_config = {
        "populate_by_name": True
    }
