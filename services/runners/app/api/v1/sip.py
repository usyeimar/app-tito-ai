"""SIP Endpoint APIs for Asterisk integration.

Provides WebSocket endpoints for chan_websocket (Asterisk 20.18+, 22.8+, 23.2+):
    - GET /sip/media/{connection_id} : Connect via WebSocket and stream audio
    - GET /sip/calls/{call_id} : Get call status
    - POST /sip/calls/{call_id}/answer : Answer
    - POST /sip/calls/{call_id}/hangup : Hangup
    - POST /sip/calls/{call_id}/transfer : Transfer to queue/peer
    - GET /sip/dialplan/{workspace} : Get dialplan rules
"""

import asyncio
import json
import uuid
from typing import Any, Optional

from fastapi import APIRouter, HTTPException, WebSocket, WebSocketDisconnect
from loguru import logger

from app.services.agents.pipelines.agent_pipeline_engine import AgentPipelineEngine
from app.services.sip.websocket_server import (
    WebSocketConnection,
    WebSocketServer,
)
from app.services.sip.call_handler import (
    CallDirection,
    CallState,
)
from app.services.sip.transport import SIPTransport

router = APIRouter(prefix="/sip", tags=["SIP"])

# Singleton WebSocket server for Asterisk chan_websocket
_websocket_server: Optional[WebSocketServer] = None


async def get_websocket_server() -> WebSocketServer:
    """Get or create the WebSocket server singleton."""
    global _websocket_server
    if _websocket_server is None:
        _websocket_server = WebSocketServer(
            host="0.0.0.0",
            port=9093,
            on_connection=handle_asterisk_connection,
        )
        await _websocket_server.start()
        logger.info("WebSocket server started on ws://0.0.0.0:9093")
    return _websocket_server


async def handle_asterisk_connection(conn: WebSocketConnection):
    """Handle a new WebSocket connection from Asterisk.

    This is called when Asterisk connects via chan_websocket.
    Extracts connection_id from MEDIA_START event and spawns the
    agent pipeline.
    """
    session_id = conn.connection_id

    logger.info(
        f"[{session_id}] New Asterisk WebSocket connection: "
        f"format={conn.format}, optimal_frame_size={conn.optimal_frame_size}"
    )

    try:
        # Wait for MEDIA_START event
        if not await conn.wait_media_start(timeout=10.0):
            logger.error(f"[{session_id}] Timeout waiting MEDIA_START")
            await conn.hangup()
            return

        # Create agent pipeline for this connection
        # The agent_id is obtained from Redis based on the DID/extension
        agent_id = await resolve_agent_from_did(session_id)

        if not agent_id:
            logger.warning(f"[{session_id}] No agent found, using default")
            agent_id = "default"

        # Initialize pipeline
        pipeline = AgentPipelineEngine(
            agent_id=agent_id,
            transport=SIPTransport(
                connection=conn,
                format=conn.format,
                sample_rate=get_sample_rate(conn.format),
            ),
        )

        # Process audio in loop
        await pipeline.run()

    except Exception as e:
        logger.error(f"[{session_id}] Pipeline error: {e}")
    finally:
        conn.close()
        logger.info(f"[{session_id}] Connection closed")


async def resolve_agent_from_did(session_id: str) -> Optional[str]:
    """Resolve agent_id from the DID/extension.

    In production, this queries Redis:
    - For Inbound: lookup extension → agent_id
    - For Outbound: return from call metadata

    Returns None if not found.
    """
    # TODO: Implement actual lookup from Redis
    # Example: redis.hget(f"trunk:routes:{workspace}", extension)
    return None


def get_sample_rate(codec: str) -> int:
    """Get sample rate for a given codec."""
    rates = {
        "ulaw": 8000,
        "alaw": 8000,
        "slin": 16000,
        "slin16": 16000,
        "slin12": 12000,
        "slin192": 192000,
        "opus": 48000,
    }
    return rates.get(codec, 8000)


# ============================================================================
# WebSocket Endpoint (Asterisk → Runner)
# ============================================================================


@router.websocket("/media/{connection_id}")
async def websocket_media(websocket: WebSocket, connection_id: str):
    """
    WebSocket para Asterisk chan_websocket (Asterisk 20.18+, 22.8+, 23.2+).

    ## Asterisk Dialplan
    ```asterisk
    ; Inbound (Asterisk espera conexión)
    exten => _X.,1,Dial(WebSocket/INCOMING/c(ulaw)n)

    ; Outbound (Asterisk conecta a nosotros)
    exten => _X.,1,Dial(WebSocket/connection1/c(ulaw))
    ```

    ## Protocolo
    - TEXT frames: JSON commands/events
    - BINARY frames: Audio PCM (ulaw/alaw/opus/slin16)

    ## Eventos
    - MEDIA_START: Conexión iniciada
    - MEDIA_XOFF/XON: Flow control
    - DTMF_END: DTMF recibido
    """
    await websocket.accept(subprotocol="websocket")

    logger.info(f"[{connection_id}] WebSocket accepted")

    conn: Optional[WebSocketConnection] = None

    try:
        # Create a simple connection wrapper (syncing with actual WebSocketServer logic)
        conn = _create_connection_wrapper(websocket, connection_id)

        # Run agent pipeline in background
        pipeline = AgentPipelineEngine(
            agent_id="default",  # TODO: resolve from DID
            transport=SIPTransport(
                connection=conn,
                format="ulaw",
                sample_rate=8000,
            ),
        )

        # Start pipeline task
        pipeline_task = asyncio.create_task(pipeline.run())

        # Handle incoming messages
        while True:
            try:
                message = await websocket.receive()

                if message["type"] == "text":
                    # Control message (TEXT frame)
                    await _handle_control_message(websocket, message["text"], conn)
                elif message["type"] == "binary":
                    # Audio data (BINARY frame)
                    audio_data = message["bytes"]
                    await pipeline.transport.send_audio(audio_data)
                elif message["type"] == "close":
                    break

            except WebSocketDisconnect:
                break

        # Cleanup
        pipeline.cancel()
        await pipeline_task

    except Exception as e:
        logger.error(f"[{connection_id}] WebSocket error: {e}")
    finally:
        await websocket.close()
        logger.info(f"[{connection_id}] WebSocket closed")


async def _handle_control_message(
    websocket: WebSocket, message: str, conn: WebSocketConnection
):
    """Handle a TEXT frame control message from Asterisk."""
    try:
        data = json.loads(message)
    except json.JSONDecodeError:
        return

    event = data.get("event")
    command = data.get("command")

    if event == "MEDIA_START":
        logger.info(f"[{conn.connection_id}] MEDIA_START: format={data.get('format')}")
    elif event == "MEDIA_XOFF":
        logger.warning(f"[{conn.connection_id}] Queue high water - pause sending")
    elif event == "MEDIA_XON":
        logger.info(f"[{conn.connection_id}] Queue low water - resume sending")
    elif event == "DTMF_END":
        digit = data.get("digit", "")
        logger.debug(f"[{conn.connection_id}] DTMF: {digit}")
    elif event == "QUEUE_DRAINED":
        logger.info(f"[{conn.connection_id}] Queue drained")


def _create_connection_wrapper(
    websocket: WebSocket, connection_id: str
) -> WebSocketConnection:
    """Create a WebSocketConnection wrapper from FastAPI WebSocket."""
    return WebSocketConnection(
        connection_id=connection_id,
        channel=f"WebSocket/{connection_id}",
        channel_id=connection_id,
        format="ulaw",
        optimal_frame_size=160,
        ptime=20,
        websocket=websocket,  # type: ignore
        remote_peer="asterisk",
    )


# ============================================================================
# Call Management Endpoints
# ============================================================================


@router.get("/calls/{call_id}")
async def get_call(call_id: str):
    """Get call status."""
    # TODO: Implement actual call status from Redis
    return {
        "call_id": call_id,
        "status": "unknown",
    }


@router.post("/calls/{call_id}/answer")
async def answer_call(call_id: str):
    """Answer an incoming call."""
    # TODO: Implement via AMI or AGI
    return {"call_id": call_id, "status": "answered"}


@router.post("/calls/{call_id}/hangup")
async def hangup_call(call_id: str):
    """Hangup a call."""
    # TODO: Implement via AMI or AGI
    return {"call_id": call_id, "status": "hungup"}


@router.post("/calls/{call_id}/transfer")
async def transfer_call(
    call_id: str, destination: str, destination_type: str = "queue"
):
    """Transfer call to queue, peer, or external number."""
    # TODO: Implement SIP REFER transfer
    return {
        "call_id": call_id,
        "destination": destination,
        "type": destination_type,
        "status": "transferred",
    }


# ============================================================================
# Dialplan Endpoints
# ============================================================================


@router.get("/dialplan/{workspace}")
async def get_dialplan(workspace: str, extension: Optional[str] = None):
    """Get dialplan rules for a workspace.

    Args:
        workspace: Workspace slug (e.g., "acme")
        extension: Optional extension to filter (e.g., "100")
    """
    # TODO: Implement from Redis or database
    return {
        "workspace": workspace,
        "routes": [],
    }


@router.post("/dialplan/{workspace}")
async def create_route(workspace: str, route: dict):
    """Create a dialplan route."""
    # TODO: Implement
    return {"route_id": str(uuid.uuid4()), **route}


# ============================================================================
# Health Check
# ============================================================================


@router.get("/health")
async def sip_health():
    """Check SIP/WebSocket server health."""
    global _websocket_server

    return {
        "status": "ok" if _websocket_server else "stopped",
        "websocket_server": "running" if _websocket_server else "stopped",
        "active_connections": len(_websocket_server.connections)
        if _websocket_server
        else 0,
    }
