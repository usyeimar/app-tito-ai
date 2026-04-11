"""Asterisk WebSocket Channel Driver (chan_websocket) server.

Implements the WebSocket protocol used by Asterisk's chan_websocket channel driver
(Asterisk 20.18+, 22.8+, 23.2+). Provides bidirectional media streaming
with control commands and events over WebSocket.

Protocol:
    - BINARY frames: Raw audio media (ulaw/alaw/opus/slin16, etc.)
    - TEXT frames: Control commands and events in JSON format (preferred)

Commands (TEXT → Asterisk):
    - ANSWER: Answer the channel
    - HANGUP: Hangup the channel
    - START_MEDIA_BUFFERING: Begin buffering mode for bulk media
    - STOP_MEDIA_BUFFERING [correlation_id]: Stop buffering, finalize frames
    - FLUSH_MEDIA: Discard queued media
    - PAUSE_MEDIA: Pause media playback to caller
    - CONTINUE_MEDIA: Resume media playback
    - MARK_MEDIA [correlation_id]: Place a mark in the media stream
    - GET_STATUS: Request status
    - REPORT_QUEUE_DRAINED: Notify when queue is empty
    - SET_MEDIA_DIRECTION: Set direction (in/out/both)

Events (TEXT → App):
    - MEDIA_START: Channel connected, contains connection_id, format, optimal_frame_size
    - DTMF_END: DTMF digit received
    - MEDIA_XOFF: Queue high water reached, stop sending
    - MEDIA_XON: Queue low water reached, safe to resume
    - STATUS: Response to GET_STATUS
    - MEDIA_BUFFERING_COMPLETED: Bulk transfer complete
    - MEDIA_MARK_PROCESSED: Mark reached front of queue
    - QUEUE_DRAINED: Queue is empty
"""

import asyncio
import json
import logging
import uuid as uuid_module
from dataclasses import dataclass, field
from enum import Enum
from typing import Callable, Awaitable, Optional, Dict, Any

import websockets

logger = logging.getLogger(__name__)


# WebSocket control message format
class ControlFormat(Enum):
    PLAIN_TEXT = "plain-text"
    JSON = "json"


# WebSocket Commands (App → Asterisk)
class WSCommand:
    ANSWER = "ANSWER"
    HANGUP = "HANGUP"
    START_MEDIA_BUFFERING = "START_MEDIA_BUFFERING"
    STOP_MEDIA_BUFFERING = "STOP_MEDIA_BUFFERING"
    FLUSH_MEDIA = "FLUSH_MEDIA"
    PAUSE_MEDIA = "PAUSE_MEDIA"
    CONTINUE_MEDIA = "CONTINUE_MEDIA"
    MARK_MEDIA = "MARK_MEDIA"
    GET_STATUS = "GET_STATUS"
    REPORT_QUEUE_DRAINED = "REPORT_QUEUE_DRAINED"
    SET_MEDIA_DIRECTION = "SET_MEDIA_DIRECTION"


# WebSocket Events (Asterisk → App)
class WSEvent:
    MEDIA_START = "MEDIA_START"
    DTMF_END = "DTMF_END"
    MEDIA_XOFF = "MEDIA_XOFF"
    MEDIA_XON = "MEDIA_XON"
    STATUS = "STATUS"
    MEDIA_BUFFERING_COMPLETED = "MEDIA_BUFFERING_COMPLETED"
    MEDIA_MARK_PROCESSED = "MEDIA_MARK_PROCESSED"
    QUEUE_DRAINED = "QUEUE_DRAINED"


class MediaDirection(Enum):
    IN = "in"
    OUT = "out"
    BOTH = "both"


@dataclass
class WebSocketConnection:
    """Represents an active WebSocket connection from Asterisk chan_websocket."""

    connection_id: str
    channel: str
    channel_id: str
    format: str
    optimal_frame_size: int
    ptime: int
    websocket: Any  # websockets.WebSocketServerProtocol (legacy, pending upgrade)
    remote_peer: str
    connected: bool = True
    media_direction: MediaDirection = MediaDirection.BOTH
    _control_format: ControlFormat = ControlFormat.JSON
    _buffering: bool = False
    _queue_drained_event: asyncio.Event = field(default_factory=asyncio.Event)
    _media_start_event: asyncio.Event = field(default_factory=asyncio.Event)

    async def send_command(self, command: str, **params) -> bool:
        """Send a control command to Asterisk via TEXT frame."""
        if not self.connected:
            return False

        try:
            if self._control_format == ControlFormat.JSON:
                payload = {"command": command}
                if params:
                    payload.update(params)
                await self.websocket.send(json.dumps(payload))
            else:
                # Plain text format (deprecated)
                parts = [command]
                for k, v in params.items():
                    parts.append(f"{k}:{v}")
                await self.websocket.send(" ".join(parts))
            return True
        except Exception as e:
            logger.warning(f"[{self.connection_id}] Send command error: {e}")
            self.connected = False
            return False

    async def send_audio(self, audio_data: bytes) -> bool:
        """Send raw audio media to Asterisk via BINARY frame."""
        if not self.connected:
            return False

        if self.media_direction == MediaDirection.IN:
            logger.debug(f"[{self.connection_id}] Dropping audio (direction=in)")
            return True

        try:
            await self.websocket.send(audio_data, binary=True)
            return True
        except Exception as e:
            logger.warning(f"[{self.connection_id}] Send audio error: {e}")
            self.connected = False
            return False

    async def answer(self) -> bool:
        """Answer the channel."""
        return await self.send_command(WSCommand.ANSWER)

    async def hangup(self) -> bool:
        """Hangup the channel."""
        return await self.send_command(WSCommand.HANGUP)

    async def start_media_buffering(self) -> bool:
        """Start buffering mode for bulk media transfer."""
        self._buffering = True
        return await self.send_command(WSCommand.START_MEDIA_BUFFERING)

    async def stop_media_buffering(self, correlation_id: Optional[str] = None) -> bool:
        """Stop buffering, finalize frames."""
        self._buffering = False
        return await self.send_command(
            WSCommand.STOP_MEDIA_BUFFERING, correlation_id=correlation_id
        )

    async def pause_media(self) -> bool:
        """Pause media playback (plays silence)."""
        return await self.send_command(WSCommand.PAUSE_MEDIA)

    async def continue_media(self) -> bool:
        """Resume media playback."""
        return await self.send_command(WSCommand.CONTINUE_MEDIA)

    async def flush_media(self) -> bool:
        """Discard queued media."""
        return await self.send_command(WSCommand.FLUSH_MEDIA)

    async def mark_media(self, correlation_id: Optional[str] = None) -> bool:
        """Place a mark in the media stream."""
        return await self.send_command(
            WSCommand.MARK_MEDIA, correlation_id=correlation_id
        )

    async def get_status(self) -> bool:
        """Request channel status."""
        return await self.send_command(WSCommand.GET_STATUS)

    async def set_media_direction(self, direction: MediaDirection) -> bool:
        """Set media direction (in/out/both)."""
        self.media_direction = direction
        return await self.send_command(
            WSCommand.SET_MEDIA_DIRECTION, direction=direction.value
        )

    async def wait_media_start(self, timeout: float = 10.0) -> bool:
        """Wait for MEDIA_START event."""
        try:
            await asyncio.wait_for(self._media_start_event.wait(), timeout=timeout)
            return True
        except asyncio.TimeoutError:
            logger.warning(f"[{self.connection_id}] Timeout waiting MEDIA_START")
            return False

    def close(self):
        """Close the WebSocket connection."""
        self.connected = False
        self._media_start_event.set()


# Type for the callback invoked when a new WebSocket connection arrives
OnConnectionCallback = Callable[[WebSocketConnection], Awaitable[None]]


class WebSocketServer:
    """WebSocket server that accepts chan_websocket connections from Asterisk.

    This server implements the Asterisk chan_websocket protocol:
    - Accepts incoming WebSocket connections on /media URI
    - Exchanges binary audio frames and text control messages
    - Handles JSON format by default (preferred over plain-text)

    Usage:
        server = WebSocketServer(
            host="0.0.0.0",
            port=9093,
            on_connection=handle_asterisk_connection
        )
        await server.start()
    """

    def __init__(
        self,
        host: str = "0.0.0.0",
        port: int = 9093,
        on_connection: Optional[OnConnectionCallback] = None,
    ):
        self._host = host
        self._port = port
        self._on_connection = on_connection
        self._server: Optional[websockets.WebSocketServer] = None
        self._connections: Dict[str, WebSocketConnection] = {}

    @property
    def connections(self) -> Dict[str, WebSocketConnection]:
        return self._connections

    async def start(self):
        """Start the WebSocket server."""
        self._server = await websockets.serve(
            self._handle_client,
            self._host,
            self._port,
            process_request=self._process_media_request,
        )
        addr = self._server.sockets[0].getsockname()
        logger.info(f"WebSocket server listening on ws://{addr[0]}:{addr[1]}")

    async def stop(self):
        """Stop the server and close all connections."""
        for conn in list(self._connections.values()):
            conn.close()
        self._connections.clear()

        if self._server:
            self._server.close()
            await self._server.wait_closed()
            logger.info("WebSocket server stopped")

    async def _process_media_request(self, path: str) -> bool:
        """Handle incoming WebSocket upgrade request.

        Asterisk connects to /media/<connection_id> for incoming
        or waits for the app to connect for outgoing.
        """
        return path.startswith("/media/")

    async def _handle_client(
        self,
        websocket: websockets.WebSocketServerProtocol,
        path: str,
    ):
        """Handle a new WebSocket connection from Asterisk."""
        peer = websocket.remote_role
        peer_str = f"{peer[0]}:{peer[1]}" if peer else "unknown"
        logger.info(f"WebSocket connection from {peer_str}")

        conn = None
        try:
            # Receive MEDIA_START event from Asterisk
            async for message in websocket:
                if isinstance(message, bytes):
                    # BINARY frame = audio media (handle in pipeline)
                    logger.debug(
                        f"[{conn and conn.connection_id}] Binary frame received"
                    )
                    continue

                # TEXT frame = control command/event
                try:
                    data = json.loads(message)
                except json.JSONDecodeError:
                    logger.warning(f"Invalid JSON: {message[:100]}")
                    continue

                event = data.get("event")
                command = data.get("command")

                if event == WSEvent.MEDIA_START:
                    # Initial connection event
                    connection_id = data.get("connection_id", str(uuid_module.uuid4()))
                    conn = WebSocketConnection(
                        connection_id=connection_id,
                        channel=data.get("channel", "WebSocket/unknown"),
                        channel_id=data.get("channel_id", ""),
                        format=data.get("format", "ulaw"),
                        optimal_frame_size=data.get("optimal_frame_size", 160),
                        ptime=data.get("ptime", 20),
                        websocket=websocket,
                        remote_peer=peer_str,
                        _control_format=ControlFormat.JSON,
                    )

                    # Check for channel variables
                    channel_vars = data.get("channel_variables", {})
                    if channel_vars:
                        logger.debug(f"[{connection_id}] Channel vars: {channel_vars}")

                    self._connections[connection_id] = conn
                    conn._media_start_event.set()
                    logger.info(
                        f"[{connection_id}] Media started: format={conn.format}, "
                        f"optimal_frame_size={conn.optimal_frame_size}, ptime={conn.ptime}"
                    )

                    if self._on_connection:
                        await self._on_connection(conn)

                elif event == WSEvent.MEDIA_XOFF:
                    logger.warning(
                        f"[{conn and conn.connection_id}] Queue high water reached"
                    )
                elif event == WSEvent.MEDIA_XON:
                    logger.info(
                        f"[{conn and conn.connection_id}] Queue low water reached"
                    )
                elif event == WSEvent.STATUS and conn:
                    status = data.get("queue_length", 0)
                    logger.debug(f"[{conn.connection_id}] Status: queue={status}")
                elif event == WSEvent.MEDIA_BUFFERING_COMPLETED:
                    logger.info(f"[{conn and conn.connection_id}] Buffering complete")
                elif event == WSEvent.MEDIA_MARK_PROCESSED:
                    logger.info(f"[{conn and conn.connection_id}] Mark processed")
                elif event == WSEvent.QUEUE_DRAINED:
                    logger.info(f"[{conn and conn.connection_id}] Queue drained")
                    if conn:
                        conn._queue_drained_event.set()
                elif event == WSEvent.DTMF_END:
                    digit = data.get("digit", "")
                    logger.debug(f"[{conn and conn.connection_id}] DTMF: {digit}")
                elif command:
                    # Command acknowledgment (Asterisk echoes commands)
                    logger.debug(
                        f"[{conn and conn.connection_id}] Command ack: {command}"
                    )

        except websockets.exceptions.ConnectionClosed:
            logger.info(f"[{conn and conn.connection_id}] Connection closed")
        except Exception as e:
            logger.error(f"[{conn and conn.connection_id}] Handler error: {e}")
        finally:
            if conn:
                conn.close()
                if conn.connection_id in self._connections:
                    del self._connections[conn.connection_id]
                logger.info(f"[{conn.connection_id}] WebSocket connection closed")


async def parse_control_message(message: str) -> Dict[str, Any]:
    """Parse a control message (JSON or plain-text).

    Args:
        message: TEXT frame content (JSON or plain-text)

    Returns:
        Parsed message as dict
    """
    try:
        return json.loads(message)
    except json.JSONDecodeError:
        # Try plain-text format: COMMAND key:value key:value
        parts = message.strip().split()
        if not parts:
            return {}

        result = {"command": parts[0]}
        for part in parts[1:]:
            if ":" in part:
                key, value = part.split(":", 1)
                result[key] = value
        return result


async def build_plain_text_command(command: str, **params) -> str:
    """Build a plain-text format command (deprecated)."""
    parts = [command]
    for k, v in params.items():
        parts.append(f"{k}:{v}")
    return " ".join(parts)
