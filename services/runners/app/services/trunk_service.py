"""Redis-backed service for managing SIP Trunks (inbound, register, outbound)."""

import json
import logging
import time
import uuid
from copy import deepcopy
from typing import Optional, Dict, Any, List

from app.services.session_manager import session_manager
from app.schemas.trunks import (
    CreateTrunkRequest,
    UpdateTrunkRequest,
    TrunkRouteConfig,
    OutboundCallRequest,
)

logger = logging.getLogger(__name__)


class TrunkService:
    """Gestión de SIP Trunks en Redis."""

    DOMAIN_SUFFIX = "sip.tito.ai"
    CALL_TTL = 3600  # 1 hora

    def __init__(self):
        self._redis = session_manager._redis
        self._ami = None  # Set via set_ami_controller()

    def set_ami_controller(self, ami):
        """Inject the AMI controller for outbound call origination."""
        self._ami = ami

    # ── CRUD Trunk ────────────────────────────────────────────────────────────

    async def create_trunk(self, request: CreateTrunkRequest) -> dict:
        trunk_id = f"trk_{uuid.uuid4().hex[:12]}"
        now = time.time()

        trunk_data: Dict[str, Any] = {
            "trunk_id": trunk_id,
            "name": request.name,
            "tenant_id": request.tenant_id,
            "workspace_slug": request.workspace_slug,
            "mode": request.mode,
            "max_concurrent_calls": request.max_concurrent_calls,
            "codecs": request.codecs,
            "status": "active",
            "created_at": now,
            "updated_at": now,
        }

        if request.mode == "inbound":
            auth = request.inbound_auth
            if auth.auth_type == "digest":
                if not auth.username:
                    auth.username = f"trk_{uuid.uuid4().hex[:8]}"
                if not auth.password:
                    auth.password = uuid.uuid4().hex[:16]
            trunk_data["inbound_auth"] = auth.model_dump()
            trunk_data["routes"] = [r.model_dump() for r in request.routes]
            trunk_data["sip_host"] = f"{request.workspace_slug}.{self.DOMAIN_SUFFIX}"
            trunk_data["sip_port"] = 5060

        elif request.mode == "register":
            trunk_data["register_config"] = request.register_config.model_dump()
            trunk_data["agent_id"] = request.agent_id
            trunk_data["registration_status"] = "unregistered"

        elif request.mode == "outbound":
            trunk_data["outbound"] = request.outbound.model_dump()
            trunk_data["total_calls_made"] = 0

        await self._redis.set(f"trunk:{trunk_id}", json.dumps(trunk_data))
        await self._redis.sadd(f"trunk:index:{request.workspace_slug}", trunk_id)

        logger.info(
            f"Trunk created | trunk_id={trunk_id} mode={request.mode} workspace={request.workspace_slug}"
        )
        return trunk_data

    async def get_trunk(self, trunk_id: str) -> Optional[dict]:
        data = await self._get_trunk_raw(trunk_id)
        if not data:
            return None
        return self._mask_passwords(data)

    async def list_trunks(self, workspace_slug: str) -> List[dict]:
        trunk_ids = await self._redis.smembers(f"trunk:index:{workspace_slug}")
        trunks = []
        for tid in trunk_ids:
            trunk = await self.get_trunk(tid)
            if trunk:
                active = await self._get_active_calls(tid)
                trunk["active_calls"] = active
                trunks.append(trunk)
        return trunks

    async def update_trunk(self, trunk_id: str, request: UpdateTrunkRequest) -> Optional[dict]:
        data = await self._get_trunk_raw(trunk_id)
        if not data:
            return None

        updates = request.model_dump(exclude_none=True)
        for key, value in updates.items():
            if key in ("inbound_auth", "register", "outbound") and isinstance(value, dict):
                data[key] = value
            elif key == "enabled":
                data["status"] = "active" if value else "inactive"
            else:
                data[key] = value

        data["updated_at"] = time.time()
        await self._redis.set(f"trunk:{trunk_id}", json.dumps(data))

        logger.info(f"Trunk updated | trunk_id={trunk_id}")
        return self._mask_passwords(data)

    async def delete_trunk(self, trunk_id: str) -> bool:
        data = await self._get_trunk_raw(trunk_id)
        if not data:
            return False

        workspace = data.get("workspace_slug")
        await self._redis.srem(f"trunk:index:{workspace}", trunk_id)
        await self._redis.delete(f"trunk:{trunk_id}")
        await self._redis.delete(f"trunk:calls:{trunk_id}")

        # Limpiar llamadas activas si es outbound
        if data.get("mode") == "outbound":
            call_ids = await self._redis.smembers(f"call:index:{trunk_id}")
            for cid in call_ids:
                await self._redis.delete(f"call:{cid}")
            await self._redis.delete(f"call:index:{trunk_id}")

        logger.info(f"Trunk deleted | trunk_id={trunk_id}")
        return True

    # ── Rutas (solo mode=inbound) ─────────────────────────────────────────────

    async def add_route(self, trunk_id: str, route: TrunkRouteConfig) -> Optional[dict]:
        data = await self._get_trunk_raw(trunk_id)
        if not data:
            return None
        if data["mode"] != "inbound":
            raise ValueError("Las rutas solo son válidas para trunks mode=inbound")

        routes = data.get("routes", [])
        for existing in routes:
            if existing["extension"] == route.extension:
                raise ValueError(f"La extensión {route.extension} ya existe en este trunk")

        routes.append(route.model_dump())
        data["routes"] = routes
        data["updated_at"] = time.time()

        await self._redis.set(f"trunk:{trunk_id}", json.dumps(data))
        logger.info(f"Route added | trunk_id={trunk_id} ext={route.extension} agent={route.agent_id}")
        return data

    async def remove_route(self, trunk_id: str, extension: str) -> bool:
        data = await self._get_trunk_raw(trunk_id)
        if not data:
            return False
        if data["mode"] != "inbound":
            raise ValueError("Las rutas solo son válidas para trunks mode=inbound")

        routes = data.get("routes", [])
        original_count = len(routes)
        routes = [r for r in routes if r["extension"] != extension]

        if len(routes) == original_count:
            return False

        data["routes"] = routes
        data["updated_at"] = time.time()
        await self._redis.set(f"trunk:{trunk_id}", json.dumps(data))

        logger.info(f"Route removed | trunk_id={trunk_id} ext={extension}")
        return True

    # ── Credenciales ──────────────────────────────────────────────────────────

    async def rotate_credentials(self, trunk_id: str) -> Optional[dict]:
        data = await self._get_trunk_raw(trunk_id)
        if not data:
            return None

        new_password = uuid.uuid4().hex[:16]
        mode = data["mode"]

        if mode == "inbound" and data.get("inbound_auth"):
            data["inbound_auth"]["password"] = new_password
        elif mode == "register" and data.get("register_config"):
            data["register_config"]["password"] = new_password
        elif mode == "outbound" and data.get("outbound"):
            data["outbound"]["password"] = new_password

        data["updated_at"] = time.time()
        await self._redis.set(f"trunk:{trunk_id}", json.dumps(data))

        logger.info(f"Credentials rotated | trunk_id={trunk_id} mode={mode}")
        return data

    # ── Resolución de llamadas (usado por SIP Bridge) ─────────────────────────

    async def resolve_inbound_call(
        self, workspace_slug: str, extension: str
    ) -> Optional[dict]:
        trunk_ids = await self._redis.smembers(f"trunk:index:{workspace_slug}")

        for tid in trunk_ids:
            data = await self._get_trunk_raw(tid)
            if not data or data["mode"] != "inbound" or data["status"] != "active":
                continue

            for route in data.get("routes", []):
                if route["extension"] == extension and route.get("enabled", True):
                    return {
                        "trunk_id": tid,
                        "agent_id": route["agent_id"],
                        "trunk_data": data,
                    }
        return None

    async def resolve_register_call(self, trunk_id: str) -> Optional[dict]:
        data = await self._get_trunk_raw(trunk_id)
        if not data or data["mode"] != "register" or data["status"] != "active":
            return None

        return {
            "trunk_id": trunk_id,
            "agent_id": data.get("agent_id"),
            "trunk_data": data,
        }

    # ── Llamadas salientes (mode=outbound) ────────────────────────────────────

    async def originate_call(self, trunk_id: str, request: OutboundCallRequest) -> Optional[dict]:
        data = await self._get_trunk_raw(trunk_id)
        if not data:
            return None
        if data["mode"] != "outbound":
            raise ValueError("originate_call solo es válido para trunks mode=outbound")
        if data["status"] != "active":
            raise ValueError("El trunk no está activo")

        # Validar concurrencia
        active = await self.increment_active_calls(trunk_id)
        if active > data["max_concurrent_calls"]:
            await self.decrement_active_calls(trunk_id)
            raise ValueError(
                f"Límite de llamadas concurrentes alcanzado ({data['max_concurrent_calls']})"
            )

        call_id = f"call_{uuid.uuid4().hex[:12]}"
        outbound_cfg = data.get("outbound", {})
        caller_id = request.caller_id or outbound_cfg.get("caller_id")

        call_data = {
            "call_id": call_id,
            "trunk_id": trunk_id,
            "agent_id": request.agent_id,
            "to": request.to,
            "caller_id": caller_id,
            "call_status": "queued",
            "session_id": None,
            "timeout_seconds": request.timeout_seconds,
            "callback_url": request.callback_url,
            "metadata": request.metadata,
            "created_at": time.time(),
        }

        await self._redis.setex(f"call:{call_id}", self.CALL_TTL, json.dumps(call_data))
        await self._redis.sadd(f"call:index:{trunk_id}", call_id)

        # Incrementar total_calls_made
        data["total_calls_made"] = data.get("total_calls_made", 0) + 1
        data["updated_at"] = time.time()
        await self._redis.set(f"trunk:{trunk_id}", json.dumps(data))

        # Ejecutar Originate via AMI si está disponible
        if self._ami and self._ami.connected:
            trunk_name = outbound_cfg.get("trunk_name", data.get("name", "default"))
            dial_string = f"PJSIP/{request.to}@{trunk_name}"

            try:
                await self._ami.originate(
                    channel=dial_string,
                    context="tito-outbound",
                    exten="s",
                    priority="1",
                    caller_id=caller_id,
                    variables={
                        "CALL_ID": call_id,
                        "AGENT_ID": request.agent_id,
                        "TRUNK_ID": trunk_id,
                    },
                    timeout=request.timeout_seconds * 1000,
                )
                call_data["call_status"] = "ringing"
                await self._redis.setex(f"call:{call_id}", self.CALL_TTL, json.dumps(call_data))
            except Exception as e:
                logger.error(f"AMI originate failed: {e}")
                call_data["call_status"] = "failed"
                await self._redis.setex(f"call:{call_id}", self.CALL_TTL, json.dumps(call_data))
                await self.decrement_active_calls(trunk_id)
                raise ValueError(f"No se pudo originar la llamada: {e}")
        else:
            logger.warning(
                f"AMI not available — call {call_id} queued but not originated. "
                "Set SIP_ENABLED=true and configure AMI credentials."
            )

        logger.info(
            f"Call originated | call_id={call_id} trunk_id={trunk_id} to={request.to} agent={request.agent_id}"
        )
        return call_data

    async def get_call(self, call_id: str) -> Optional[dict]:
        raw = await self._redis.get(f"call:{call_id}")
        return json.loads(raw) if raw else None

    async def list_calls(self, trunk_id: str) -> List[dict]:
        call_ids = await self._redis.smembers(f"call:index:{trunk_id}")
        calls = []
        for cid in call_ids:
            call = await self.get_call(cid)
            if call:
                calls.append(call)
            else:
                # Call TTL expiró, limpiar del índice
                await self._redis.srem(f"call:index:{trunk_id}", cid)
        return calls

    async def cancel_call(self, call_id: str) -> Optional[dict]:
        call = await self.get_call(call_id)
        if not call:
            return None

        if call["call_status"] not in ("queued", "ringing"):
            raise ValueError(
                f"No se puede cancelar una llamada con status={call['call_status']}"
            )

        call["call_status"] = "cancelled"
        await self._redis.setex(f"call:{call_id}", self.CALL_TTL, json.dumps(call))
        await self._redis.srem(f"call:index:{call['trunk_id']}", call_id)
        await self.decrement_active_calls(call["trunk_id"])

        logger.info(f"Call cancelled | call_id={call_id}")
        return call

    async def update_call_status(
        self, call_id: str, new_status: str, session_id: Optional[str] = None
    ) -> Optional[dict]:
        call = await self.get_call(call_id)
        if not call:
            return None

        call["call_status"] = new_status
        if session_id:
            call["session_id"] = session_id

        await self._redis.setex(f"call:{call_id}", self.CALL_TTL, json.dumps(call))

        # Si es un estado terminal, limpiar contadores
        terminal_statuses = ("completed", "failed", "no_answer", "busy", "cancelled")
        if new_status in terminal_statuses:
            await self._redis.srem(f"call:index:{call['trunk_id']}", call_id)
            await self.decrement_active_calls(call["trunk_id"])

        logger.info(f"Call status updated | call_id={call_id} status={new_status}")
        return call

    # ── Helpers ───────────────────────────────────────────────────────────────

    async def _get_trunk_raw(self, trunk_id: str) -> Optional[dict]:
        raw = await self._redis.get(f"trunk:{trunk_id}")
        return json.loads(raw) if raw else None

    def _mask_passwords(self, data: dict) -> dict:
        masked = deepcopy(data)
        if masked.get("inbound_auth", {}).get("password"):
            masked["inbound_auth"]["password"] = "********"
        if masked.get("register_config", {}).get("password"):
            masked["register_config"]["password"] = "********"
        if masked.get("outbound", {}).get("password"):
            masked["outbound"]["password"] = "********"
        return masked

    async def _get_active_calls(self, trunk_id: str) -> int:
        val = await self._redis.get(f"trunk:calls:{trunk_id}")
        return int(val) if val else 0

    async def increment_active_calls(self, trunk_id: str) -> int:
        return await self._redis.incr(f"trunk:calls:{trunk_id}")

    async def decrement_active_calls(self, trunk_id: str) -> int:
        val = await self._redis.decr(f"trunk:calls:{trunk_id}")
        if val < 0:
            await self._redis.set(f"trunk:calls:{trunk_id}", 0)
            return 0
        return val


trunk_service = TrunkService()
