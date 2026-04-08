import time
from typing import Optional, Literal, Dict, Any, List

from pydantic import BaseModel, Field, ConfigDict, model_validator


# ── Configs por modo ──────────────────────────────────────────────────────────


class TrunkRouteConfig(BaseModel):
    """Mapeo de extensión/DID a un agente (solo para mode=inbound)."""

    extension: str = Field(
        ...,
        description="Extensión o DID que se marcó.",
        examples=["100", "+573001234567"],
    )
    agent_id: str = Field(
        ...,
        description="ID del agente que atenderá.",
        examples=["luna-soporte"],
    )
    priority: int = Field(0, description="Prioridad de routing (menor = más prioritario).")
    enabled: bool = Field(True, description="Si esta ruta está activa.")


class TrunkInboundAuthConfig(BaseModel):
    """Autenticación para trunks inbound (el cliente se conecta a ti)."""

    auth_type: Literal["digest", "ip"] = Field(..., description="Tipo de autenticación SIP.")
    username: Optional[str] = Field(
        None, description="Usuario para Digest Auth (auto-generado si no se provee)."
    )
    password: Optional[str] = Field(
        None, description="Password para Digest Auth (auto-generado si no se provee)."
    )
    allowed_ips: List[str] = Field(
        default_factory=list,
        description="IPs permitidas (solo para auth_type=ip).",
        examples=[["203.0.113.10"]],
    )


class TrunkRegisterConfig(BaseModel):
    """Configuración para registrarse en una PBX remota (mode=register)."""

    remote_host: str = Field(
        ..., description="Host/IP de la PBX del cliente.", examples=["pbx.alloy.com"]
    )
    remote_port: int = Field(5060, description="Puerto SIP de la PBX remota.")
    username: str = Field(
        ..., description="Usuario/extensión en la PBX remota.", examples=["100"]
    )
    password: str = Field(..., description="Password de la extensión remota.")
    domain: Optional[str] = Field(None, description="Dominio SIP si difiere del host.")
    transport: Literal["udp", "tcp", "tls"] = Field(
        "udp", description="Protocolo de transporte SIP."
    )
    register_interval: int = Field(
        120, description="Segundos entre re-registros (keepalive)."
    )


class TrunkOutboundConfig(BaseModel):
    """Configuración del carrier/proveedor SIP para llamadas salientes (mode=outbound)."""

    carrier_host: str = Field(
        ...,
        description="Host del carrier SIP.",
        examples=["sip.twilio.com", "trunk.voipms.com"],
    )
    carrier_port: int = Field(5060, description="Puerto SIP del carrier.")
    username: str = Field(
        ..., description="Usuario/cuenta en el carrier.", examples=["ACxxxxx"]
    )
    password: str = Field(..., description="Password/auth token del carrier.")
    domain: Optional[str] = Field(
        None, description="Dominio SIP del carrier (si difiere del host)."
    )
    transport: Literal["udp", "tcp", "tls"] = Field(
        "udp", description="Protocolo de transporte SIP."
    )
    caller_id: Optional[str] = Field(
        None,
        description="Número que verá el destinatario (caller ID).",
        examples=["+573001234567"],
    )
    prefix: Optional[str] = Field(
        None, description="Prefijo de marcación del carrier (ej: '9' para línea externa)."
    )
    headers: Dict[str, str] = Field(
        default_factory=dict, description="Headers SIP custom para el carrier."
    )


# ── Requests ──────────────────────────────────────────────────────────────────


class CreateTrunkRequest(BaseModel):
    """Request para crear un trunk SIP."""

    name: str = Field(
        ...,
        description="Nombre descriptivo del trunk.",
        examples=["Trunk Principal Alloy"],
    )
    tenant_id: str = Field(
        ..., description="ID del tenant/organización.", examples=["tenant-abc"]
    )
    workspace_slug: str = Field(
        ..., description="Slug del workspace.", examples=["alloy-finance"]
    )
    mode: Literal["inbound", "register", "outbound"] = Field(
        ..., description="Modo de conexión SIP."
    )

    # Para mode=inbound
    inbound_auth: Optional[TrunkInboundAuthConfig] = Field(
        None, description="Autenticación (requerido si mode=inbound)."
    )
    routes: List[TrunkRouteConfig] = Field(
        default_factory=list,
        description="Rutas iniciales ext→agente (solo mode=inbound).",
    )

    # Para mode=register
    register_config: Optional[TrunkRegisterConfig] = Field(
        None, description="Config de registro remoto (requerido si mode=register)."
    )
    agent_id: Optional[str] = Field(
        None,
        description="Agente asociado (requerido si mode=register, 1 registro = 1 agente).",
    )

    # Para mode=outbound
    outbound: Optional[TrunkOutboundConfig] = Field(
        None, description="Config del carrier SIP (requerido si mode=outbound)."
    )

    # Compartidos
    max_concurrent_calls: int = Field(
        5, description="Máximo de llamadas simultáneas en este trunk."
    )
    codecs: List[str] = Field(
        default_factory=lambda: ["ulaw", "alaw", "opus"],
        description="Codecs SIP permitidos.",
    )

    @model_validator(mode="after")
    def validate_mode_fields(self):
        if self.mode == "inbound" and self.inbound_auth is None:
            raise ValueError("inbound_auth es requerido cuando mode=inbound")
        if self.mode == "register":
            if self.register_config is None:
                raise ValueError("register es requerido cuando mode=register")
            if not self.agent_id:
                raise ValueError("agent_id es requerido cuando mode=register")
        if self.mode == "outbound" and self.outbound is None:
            raise ValueError("outbound es requerido cuando mode=outbound")
        return self


class UpdateTrunkRequest(BaseModel):
    """Request para actualizar un trunk (campos parciales)."""

    name: Optional[str] = None
    inbound_auth: Optional[TrunkInboundAuthConfig] = None
    register_config: Optional[TrunkRegisterConfig] = None
    outbound: Optional[TrunkOutboundConfig] = None
    agent_id: Optional[str] = None
    max_concurrent_calls: Optional[int] = None
    codecs: Optional[List[str]] = None
    enabled: Optional[bool] = None


class OutboundCallRequest(BaseModel):
    """Request para iniciar una llamada saliente."""

    to: str = Field(
        ..., description="Número destino en formato E.164.", examples=["+573001234567"]
    )
    agent_id: str = Field(
        ...,
        description="ID del agente que manejará la llamada.",
        examples=["luna-cobranzas"],
    )
    caller_id: Optional[str] = Field(
        None,
        description="Override del caller ID (si no se usa, se toma del trunk).",
    )
    timeout_seconds: int = Field(
        30, description="Segundos esperando que contesten antes de desistir."
    )
    callback_url: Optional[str] = Field(
        None, description="URL para webhooks de esta llamada específica."
    )
    metadata: Dict[str, Any] = Field(
        default_factory=dict,
        description="Datos extra inyectados al contexto del agente (nombre del cliente, deuda, etc.).",
    )


# ── Responses ─────────────────────────────────────────────────────────────────


class TrunkLink(BaseModel):
    """Enlace HATEOAS."""

    href: str = Field(..., description="URL del recurso.")
    method: str = Field(..., description="Método HTTP.")


class TrunkResponse(BaseModel):
    """Respuesta completa de un trunk."""

    trunk_id: str = Field(
        ..., description="ID único del trunk.", examples=["trk_a1b2c3d4e5f6"]
    )
    name: str
    tenant_id: str
    workspace_slug: str
    mode: Literal["inbound", "register", "outbound"]

    # Inbound
    sip_host: Optional[str] = Field(
        None,
        description="Host SIP para mode=inbound.",
        examples=["alloy-finance.sip.tito.ai"],
    )
    sip_port: int = Field(5060)
    inbound_auth: Optional[TrunkInboundAuthConfig] = Field(
        None, description="Auth config (password enmascarado en GET)."
    )
    routes: List[TrunkRouteConfig] = Field(default_factory=list)

    # Register
    register_config: Optional[TrunkRegisterConfig] = Field(
        None, description="Config de registro (password enmascarado en GET)."
    )
    registration_status: Optional[
        Literal["registered", "unregistered", "rejected", "retrying"]
    ] = Field(None, description="Estado actual del registro SIP (solo mode=register).")
    agent_id: Optional[str] = Field(
        None, description="Agente asociado (mode=register)."
    )

    # Outbound
    outbound: Optional[TrunkOutboundConfig] = Field(
        None, description="Config del carrier (password enmascarado en GET)."
    )
    total_calls_made: int = Field(
        0, description="Total de llamadas originadas por este trunk."
    )

    # Compartidos
    max_concurrent_calls: int = 5
    active_calls: int = Field(0, description="Llamadas activas en este momento.")
    codecs: List[str] = Field(default_factory=list)
    status: Literal["active", "inactive", "suspended"] = "active"
    created_at: float = Field(default_factory=time.time)
    updated_at: float = Field(default_factory=time.time)
    links: Dict[str, TrunkLink] = Field(default_factory=dict, alias="_links")

    model_config = ConfigDict(
        populate_by_name=True,
        json_schema_extra={
            "example": {
                "trunk_id": "trk_a1b2c3d4e5f6",
                "name": "Trunk Principal Alloy",
                "tenant_id": "tenant-abc",
                "workspace_slug": "alloy-finance",
                "mode": "inbound",
                "sip_host": "alloy-finance.sip.tito.ai",
                "sip_port": 5060,
                "inbound_auth": {
                    "auth_type": "digest",
                    "username": "alloy-trunk",
                    "password": "********",
                },
                "routes": [
                    {"extension": "100", "agent_id": "luna-soporte", "priority": 0, "enabled": True}
                ],
                "max_concurrent_calls": 10,
                "active_calls": 2,
                "codecs": ["ulaw", "alaw"],
                "status": "active",
                "_links": {
                    "self": {"href": "/api/v1/trunks/trk_a1b2c3d4e5f6", "method": "GET"},
                    "routes": {"href": "/api/v1/trunks/trk_a1b2c3d4e5f6/routes", "method": "POST"},
                },
            }
        },
    )


class TrunkListResponse(BaseModel):
    """Lista de trunks."""

    trunks: List[TrunkResponse] = Field(default_factory=list)
    count: int = Field(..., description="Total de trunks.")
    links: Dict[str, TrunkLink] = Field(default_factory=dict, alias="_links")

    model_config = ConfigDict(populate_by_name=True)


class TrunkRouteResponse(BaseModel):
    """Respuesta al agregar una ruta."""

    trunk_id: str
    route: TrunkRouteConfig
    total_routes: int
    links: Dict[str, TrunkLink] = Field(default_factory=dict, alias="_links")

    model_config = ConfigDict(populate_by_name=True)


class TrunkCredentialsResponse(BaseModel):
    """Respuesta con credenciales regeneradas (password en claro solo aquí)."""

    trunk_id: str
    mode: Literal["inbound", "register", "outbound"]
    inbound_auth: Optional[TrunkInboundAuthConfig] = None
    register_config: Optional[TrunkRegisterConfig] = None
    outbound: Optional[TrunkOutboundConfig] = None
    status: str = "CREDENTIALS_ROTATED"
    links: Dict[str, TrunkLink] = Field(default_factory=dict, alias="_links")

    model_config = ConfigDict(populate_by_name=True)


class OutboundCallResponse(BaseModel):
    """Respuesta al iniciar una llamada saliente."""

    call_id: str = Field(
        ..., description="ID único de la llamada.", examples=["call_a1b2c3d4e5f6"]
    )
    trunk_id: str
    agent_id: str
    to: str
    caller_id: Optional[str] = None
    call_status: Literal[
        "queued", "ringing", "answered", "completed", "failed", "no_answer", "busy", "cancelled"
    ] = "queued"
    session_id: Optional[str] = Field(
        None,
        description="ID de sesión Pipecat (disponible cuando call_status=answered).",
    )
    created_at: float = Field(default_factory=time.time)
    links: Dict[str, TrunkLink] = Field(default_factory=dict, alias="_links")

    model_config = ConfigDict(populate_by_name=True)


class OutboundCallListResponse(BaseModel):
    """Lista de llamadas de un trunk outbound."""

    calls: List[OutboundCallResponse] = Field(default_factory=list)
    count: int = Field(..., description="Total de llamadas.")
    trunk_id: str
    links: Dict[str, TrunkLink] = Field(default_factory=dict, alias="_links")

    model_config = ConfigDict(populate_by_name=True)
