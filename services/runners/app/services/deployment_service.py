import json
import logging
import uuid
from typing import Optional, Dict, Any, List
from app.services.session_manager import session_manager
from app.schemas.deployments import SIPProvisionRequest
from app.schemas.widget import WidgetConfig

logger = logging.getLogger(__name__)

class DeploymentService:
    """Servicio para gestionar el despliegue de agentes en diferentes canales (SIP, Widget)."""

    def __init__(self):
        self._redis = session_manager._redis
        self.DOMAIN_SUFFIX = "sip.tito.ai"

    # --- SIP Deployments ---
    async def provision_sip(self, request: SIPProvisionRequest) -> Dict[str, Any]:
        """Crea o actualiza un despliegue SIP para un agente."""
        domain = f"{request.workspace_slug}.{self.DOMAIN_SUFFIX}"
        sip_uri = f"sip:{request.agent_id}@{domain}"
        
        # Generar o recuperar credenciales
        existing = await self.get_deployment(request.workspace_slug, request.agent_id)
        
        deployment_data = {
            "agent_id": request.agent_id,
            "workspace_slug": request.workspace_slug,
            "sip_uri": sip_uri,
            "sip_username": request.agent_id,
            "sip_password": existing.get("sip_password") if existing else uuid.uuid4().hex[:12],
            "sip_provider": request.sip_provider,
            "status": "ACTIVE",
            "api_key": request.api_key or (existing.get("api_key") if existing else f"sk_sip_{uuid.uuid4().hex[:16]}"),
            "updated_at": uuid.uuid4().hex # Para tracking de cambios
        }

        key = self._get_sip_key(request.workspace_slug, request.agent_id)
        await self._redis.set(key, json.dumps(deployment_data))
        
        logger.info(f"🚀 Deployment SIP provisioned | {sip_uri}")
        return deployment_data

    async def get_deployment(self, workspace_slug: str, agent_id: str) -> Optional[Dict[str, Any]]:
        """Busca un despliegue SIP específico."""
        key = self._get_sip_key(workspace_slug, agent_id)
        data = await self._redis.get(key)
        return json.loads(data) if data else None

    # --- Widget Deployments ---
    async def save_widget_config(self, config: WidgetConfig) -> Dict[str, Any]:
        """Guarda la configuración visual y de comportamiento de un widget."""
        key = self._get_widget_key(config.workspace_slug, config.agent_id)
        data = config.model_dump()
        await self._redis.set(key, json.dumps(data))
        logger.info(f"🎨 Widget config saved | {config.agent_id} in {config.workspace_slug}")
        return data

    async def get_widget_config(self, workspace_slug: str, agent_id: str) -> Optional[Dict[str, Any]]:
        """Recupera la configuración de un widget."""
        key = self._get_widget_key(workspace_slug, agent_id)
        data = await self._redis.get(key)
        return json.loads(data) if data else None

    async def rotate_sip_key(self, workspace_slug: str, agent_id: str) -> str:
        """Genera una nueva API Key para el despliegue SIP."""
        deployment = await self.get_deployment(workspace_slug, agent_id)
        if not deployment:
            raise ValueError("Deployment not found")

        new_key = f"sk_sip_{uuid.uuid4().hex[:16]}"
        deployment["api_key"] = new_key
        
        key = self._get_sip_key(workspace_slug, agent_id)
        await self._redis.set(key, json.dumps(deployment))
        return new_key

    async def delete_deployment(self, workspace_slug: str, agent_id: str, type: str = "sip") -> bool:
        """Elimina un despliegue (sip o widget)."""
        if type == "sip":
            key = self._get_sip_key(workspace_slug, agent_id)
        else:
            key = self._get_widget_key(workspace_slug, agent_id)
        
        result = await self._redis.delete(key)
        return result > 0

    def _get_sip_key(self, workspace: str, agent: str) -> str:
        return f"deployment:sip:{workspace}:{agent}"

    def _get_widget_key(self, workspace: str, agent: str) -> str:
        return f"deployment:widget:{workspace}:{agent}"

deployment_service = DeploymentService()
