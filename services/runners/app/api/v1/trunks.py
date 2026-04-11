import logging

from fastapi import APIRouter, HTTPException, Query, Path, Request, status

from app.schemas.sessions import ActionResponse
from app.schemas.trunks import (
    CreateTrunkRequest,
    UpdateTrunkRequest,
    TrunkRouteConfig,
    OutboundCallRequest,
    TrunkLink,
    TrunkResponse,
    TrunkListResponse,
    TrunkRouteResponse,
    TrunkCredentialsResponse,
    OutboundCallResponse,
    OutboundCallListResponse,
)
from app.services.trunk_service import trunk_service

logger = logging.getLogger(__name__)

router = APIRouter()


# ── Helpers HATEOAS ───────────────────────────────────────────────────────────


def get_trunk_links(request: Request, trunk_id: str, mode: str) -> dict:
    base_url = str(request.base_url).rstrip("/")
    trunk_path = f"{base_url}/api/v1/trunks/{trunk_id}"

    links = {
        "self": TrunkLink(href=trunk_path, method="GET"),
        "update": TrunkLink(href=trunk_path, method="PATCH"),
        "delete": TrunkLink(href=trunk_path, method="DELETE"),
        "rotate_credentials": TrunkLink(
            href=f"{trunk_path}/rotate-credentials", method="POST"
        ),
    }

    if mode == "inbound":
        links["routes"] = TrunkLink(href=f"{trunk_path}/routes", method="POST")
    elif mode == "outbound":
        links["calls"] = TrunkLink(href=f"{trunk_path}/calls", method="POST")
        links["list_calls"] = TrunkLink(href=f"{trunk_path}/calls", method="GET")

    return links


def get_call_links(request: Request, trunk_id: str, call_id: str) -> dict:
    base_url = str(request.base_url).rstrip("/")
    call_path = f"{base_url}/api/v1/trunks/{trunk_id}/calls/{call_id}"

    return {
        "self": TrunkLink(href=call_path, method="GET"),
        "cancel": TrunkLink(href=call_path, method="DELETE"),
        "trunk": TrunkLink(href=f"{base_url}/api/v1/trunks/{trunk_id}", method="GET"),
    }


# ── CRUD Trunks ───────────────────────────────────────────────────────────────


@router.post(
    "/",
    status_code=status.HTTP_201_CREATED,
    response_model=TrunkResponse,
    summary="Crear SIP Trunk",
    response_description="Trunk creado exitosamente con credenciales de conexión.",
    responses={
        201: {"model": TrunkResponse, "description": "Trunk creado."},
        422: {"description": "Validación fallida (campos requeridos según mode)."},
    },
)
async def create_trunk(request_body: CreateTrunkRequest, request: Request):
    """
    Crea un nuevo SIP Trunk.

    **Modos soportados:**

    - **`inbound`**: La PBX del cliente se conecta a tu Asterisk. Requiere `inbound_auth`.
      Soporta múltiples rutas extensión→agente.
    - **`register`**: Tu Asterisk se registra como extensión en la PBX del cliente.
      Requiere `register` + `agent_id`. 1 registro = 1 agente.
    - **`outbound`**: Para originar llamadas salientes via un carrier SIP.
      Requiere `outbound` con datos del carrier.
    """
    try:
        trunk = await trunk_service.create_trunk(request_body)
        active = await trunk_service._get_active_calls(trunk["trunk_id"])
        trunk["active_calls"] = active
        trunk["_links"] = get_trunk_links(request, trunk["trunk_id"], trunk["mode"])
        return trunk
    except ValueError as e:
        raise HTTPException(status_code=422, detail=str(e))


@router.get(
    "/",
    response_model=TrunkListResponse,
    summary="Listar SIP Trunks",
    response_description="Lista de trunks del workspace.",
)
async def list_trunks(
    request: Request,
    workspace_slug: str = Query(
        ..., description="Slug del workspace.", examples=["alloy-finance"]
    ),
):
    """
    Lista todos los SIP Trunks de un workspace.

    Incluye trunks de todos los modos (inbound, register, outbound).
    """
    trunks = await trunk_service.list_trunks(workspace_slug)
    for t in trunks:
        t["_links"] = get_trunk_links(request, t["trunk_id"], t["mode"])

    base_url = str(request.base_url).rstrip("/")
    return TrunkListResponse(
        trunks=trunks,
        count=len(trunks),
        _links={
            "self": TrunkLink(
                href=f"{base_url}/api/v1/trunks?workspace_slug={workspace_slug}",
                method="GET",
            ),
            "create": TrunkLink(href=f"{base_url}/api/v1/trunks", method="POST"),
        },
    )


@router.get(
    "/{trunk_id}",
    response_model=TrunkResponse,
    summary="Obtener SIP Trunk",
    response_description="Detalle del trunk (passwords enmascarados).",
    responses={404: {"description": "Trunk no encontrado."}},
)
async def get_trunk(
    request: Request,
    trunk_id: str = Path(
        ..., description="ID del trunk.", examples=["trk_a1b2c3d4e5f6"]
    ),
):
    """Obtiene los datos de un SIP Trunk por ID. Los passwords se enmascaran."""
    trunk = await trunk_service.get_trunk(trunk_id)
    if not trunk:
        raise HTTPException(status_code=404, detail="Trunk no encontrado.")

    active = await trunk_service._get_active_calls(trunk_id)
    trunk["active_calls"] = active
    trunk["_links"] = get_trunk_links(request, trunk_id, trunk["mode"])
    return trunk


@router.patch(
    "/{trunk_id}",
    response_model=TrunkResponse,
    summary="Actualizar SIP Trunk",
    response_description="Trunk actualizado.",
    responses={404: {"description": "Trunk no encontrado."}},
)
async def update_trunk(
    request_body: UpdateTrunkRequest,
    request: Request,
    trunk_id: str = Path(...),
):
    """
    Actualiza parcialmente un SIP Trunk.

    Solo se modifican los campos enviados. Para desactivar un trunk, enviar `"enabled": false`.
    """
    trunk = await trunk_service.update_trunk(trunk_id, request_body)
    if not trunk:
        raise HTTPException(status_code=404, detail="Trunk no encontrado.")

    active = await trunk_service._get_active_calls(trunk_id)
    trunk["active_calls"] = active
    trunk["_links"] = get_trunk_links(request, trunk_id, trunk["mode"])
    return trunk


@router.delete(
    "/{trunk_id}",
    response_model=ActionResponse,
    summary="Eliminar SIP Trunk",
    responses={404: {"description": "Trunk no encontrado."}},
)
async def delete_trunk(trunk_id: str = Path(...)):
    """
    Elimina un SIP Trunk y todos sus datos asociados.

    Si el trunk es outbound, también elimina las llamadas activas.
    """
    deleted = await trunk_service.delete_trunk(trunk_id)
    if not deleted:
        raise HTTPException(status_code=404, detail="Trunk no encontrado.")

    return ActionResponse(
        success=True, message=f"Trunk {trunk_id} eliminado exitosamente."
    )


# ── Rutas (solo mode=inbound) ────────────────────────────────────────────────


@router.post(
    "/{trunk_id}/routes",
    status_code=status.HTTP_201_CREATED,
    response_model=TrunkRouteResponse,
    summary="Agregar Ruta a Trunk",
    responses={
        404: {"description": "Trunk no encontrado."},
        409: {"description": "Extensión duplicada."},
        400: {"description": "El trunk no es mode=inbound."},
    },
)
async def add_route(
    route: TrunkRouteConfig,
    request: Request,
    trunk_id: str = Path(...),
):
    """
    Agrega una ruta extensión→agente a un trunk inbound.

    Solo válido para trunks con `mode=inbound`. La extensión debe ser única dentro del trunk.
    """
    try:
        data = await trunk_service.add_route(trunk_id, route)
    except ValueError as e:
        msg = str(e)
        if "ya existe" in msg:
            raise HTTPException(status_code=409, detail=msg)
        raise HTTPException(status_code=400, detail=msg)

    if not data:
        raise HTTPException(status_code=404, detail="Trunk no encontrado.")

    return TrunkRouteResponse(
        trunk_id=trunk_id,
        route=route,
        total_routes=len(data.get("routes", [])),
        _links=get_trunk_links(request, trunk_id, "inbound"),
    )


@router.delete(
    "/{trunk_id}/routes/{extension}",
    response_model=ActionResponse,
    summary="Eliminar Ruta de Trunk",
    responses={
        404: {"description": "Trunk o extensión no encontrada."},
        400: {"description": "El trunk no es mode=inbound."},
    },
)
async def remove_route(
    trunk_id: str = Path(...),
    extension: str = Path(..., description="Extensión a eliminar.", examples=["100"]),
):
    """Elimina una ruta extensión→agente de un trunk inbound."""
    try:
        removed = await trunk_service.remove_route(trunk_id, extension)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))

    if not removed:
        raise HTTPException(status_code=404, detail="Trunk o extensión no encontrada.")

    return ActionResponse(
        success=True,
        message=f"Ruta para extensión {extension} eliminada del trunk {trunk_id}.",
    )


# ── Credenciales ──────────────────────────────────────────────────────────────


@router.post(
    "/{trunk_id}/rotate-credentials",
    response_model=TrunkCredentialsResponse,
    summary="Rotar Credenciales del Trunk",
    responses={404: {"description": "Trunk no encontrado."}},
)
async def rotate_credentials(
    request: Request,
    trunk_id: str = Path(...),
):
    """
    Genera un nuevo password para el trunk.

    El password anterior deja de funcionar inmediatamente.
    Esta es la única respuesta donde el password se muestra en claro.
    """
    data = await trunk_service.rotate_credentials(trunk_id)
    if not data:
        raise HTTPException(status_code=404, detail="Trunk no encontrado.")

    return TrunkCredentialsResponse(
        trunk_id=trunk_id,
        mode=data["mode"],
        inbound_auth=data.get("inbound_auth"),
        register_config=data.get("register_config"),
        outbound=data.get("outbound"),
        _links=get_trunk_links(request, trunk_id, data["mode"]),
    )


# ── Llamadas salientes (solo mode=outbound) ──────────────────────────────────


@router.post(
    "/{trunk_id}/calls",
    status_code=status.HTTP_201_CREATED,
    response_model=OutboundCallResponse,
    summary="Originar Llamada Saliente",
    responses={
        400: {"description": "El trunk no es mode=outbound o no está activo."},
        404: {"description": "Trunk no encontrado."},
        429: {"description": "Límite de llamadas concurrentes alcanzado."},
    },
)
async def originate_call(
    call_request: OutboundCallRequest,
    request: Request,
    trunk_id: str = Path(...),
):
    """
    Inicia una llamada saliente via un trunk outbound.

    El agente IA llamará al número indicado. El campo `metadata` se inyecta
    al contexto del agente para que sepa con quién habla y por qué.

    **Estados de la llamada:**
    - `queued` → En cola para originar
    - `ringing` → El teléfono está sonando
    - `answered` → El usuario contestó, pipeline activo
    - `completed` → Llamada terminada normalmente
    - `failed` → Error de conexión
    - `no_answer` → Timeout, nadie contestó
    - `busy` → Línea ocupada
    - `cancelled` → Cancelada via API
    """
    try:
        call = await trunk_service.originate_call(trunk_id, call_request)
    except ValueError as e:
        msg = str(e)
        if "Límite" in msg:
            raise HTTPException(
                status_code=429, detail=msg, headers={"Retry-After": "10"}
            )
        if "no está activo" in msg or "solo es válido" in msg:
            raise HTTPException(status_code=400, detail=msg)
        raise HTTPException(status_code=400, detail=msg)

    if not call:
        raise HTTPException(status_code=404, detail="Trunk no encontrado.")

    call["_links"] = get_call_links(request, trunk_id, call["call_id"])
    return call


@router.get(
    "/{trunk_id}/calls",
    response_model=OutboundCallListResponse,
    summary="Listar Llamadas Activas del Trunk",
    responses={404: {"description": "Trunk no encontrado."}},
)
async def list_calls(
    request: Request,
    trunk_id: str = Path(...),
):
    """Lista las llamadas activas (no terminadas) de un trunk outbound."""
    trunk = await trunk_service.get_trunk(trunk_id)
    if not trunk:
        raise HTTPException(status_code=404, detail="Trunk no encontrado.")

    calls = await trunk_service.list_calls(trunk_id)
    for c in calls:
        c["_links"] = get_call_links(request, trunk_id, c["call_id"])

    base_url = str(request.base_url).rstrip("/")
    return OutboundCallListResponse(
        calls=calls,
        count=len(calls),
        trunk_id=trunk_id,
        _links={
            "self": TrunkLink(
                href=f"{base_url}/api/v1/trunks/{trunk_id}/calls", method="GET"
            ),
            "create": TrunkLink(
                href=f"{base_url}/api/v1/trunks/{trunk_id}/calls", method="POST"
            ),
            "trunk": TrunkLink(
                href=f"{base_url}/api/v1/trunks/{trunk_id}", method="GET"
            ),
        },
    )


@router.get(
    "/{trunk_id}/calls/{call_id}",
    response_model=OutboundCallResponse,
    summary="Obtener Estado de Llamada",
    responses={404: {"description": "Llamada no encontrada."}},
)
async def get_call(
    request: Request,
    trunk_id: str = Path(...),
    call_id: str = Path(...),
):
    """Obtiene el estado actual de una llamada saliente."""
    call = await trunk_service.get_call(call_id)
    if not call or call.get("trunk_id") != trunk_id:
        raise HTTPException(status_code=404, detail="Llamada no encontrada.")

    call["_links"] = get_call_links(request, trunk_id, call_id)
    return call


@router.delete(
    "/{trunk_id}/calls/{call_id}",
    response_model=OutboundCallResponse,
    summary="Cancelar Llamada Saliente",
    responses={
        400: {"description": "La llamada no se puede cancelar en su estado actual."},
        404: {"description": "Llamada no encontrada."},
    },
)
async def cancel_call(
    request: Request,
    trunk_id: str = Path(...),
    call_id: str = Path(...),
):
    """
    Cancela una llamada saliente.

    Solo se pueden cancelar llamadas con status `queued` o `ringing`.
    """
    try:
        call = await trunk_service.cancel_call(call_id)
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))

    if not call:
        raise HTTPException(status_code=404, detail="Llamada no encontrada.")

    call["_links"] = get_call_links(request, trunk_id, call_id)
    return call
