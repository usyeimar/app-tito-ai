"""Asterisk AudioSocket TCP server.

Implements the AudioSocket protocol used by Asterisk's chan_audiosocket.
When a call arrives, Asterisk opens a TCP connection and streams bidirectional
raw audio (signed linear 16-bit, various sample rates).

Protocol (per https://docs.asterisk.org/Configuration/Channel-Drivers/AudioSocket/):
    Each frame: [type:1 byte][length:2 bytes big-endian][payload:N bytes]

    Types:
        0x00 - Hangup (no payload)
        0x01 - UUID (16-byte binary channel UUID)
        0x03 - DTMF (1-byte ascii digit)
        0x10-0x18 - Audio (different sample rates)
        0xff - Error (optional error code)

Sample rates:
        0x10 = 8kHz,  0x11 = 12kHz,  0x12 = 16kHz,  0x13 = 24kHz
        0x14 = 32kHz, 0x15 = 44.1kHz, 0x16 = 48kHz, 0x17 = 96kHz, 0x18 = 192kHz
"""

import asyncio
import logging
import struct
from dataclasses import dataclass, field
from typing import Callable, Awaitable, Optional, Dict

logger = logging.getLogger(__name__)

# AudioSocket frame types
AUDIOSOCKET_TYPE_HANGUP = 0x00
AUDIOSOCKET_TYPE_UUID = 0x01
AUDIOSOCKET_TYPE_DTMF = 0x03
AUDIOSOCKET_TYPE_AUDIO_BASE = 0x10

# Extended audio types (different sample rates)
AUDIO_SAMPLE_RATES = {
    0x10: (8000, 160, "slin8"),  # 8kHz, 160 bytes = 20ms
    0x11: (12000, 240, "slin12"),  # 12kHz
    0x12: (16000, 320, "slin16"),  # 16kHz (default)
    0x13: (24000, 480, "slin24"),  # 24kHz
    0x14: (32000, 640, "slin32"),  # 32kHz
    0x15: (44100, 882, "slin44"),  # 44.1kHz
    0x16: (48000, 960, "slin48"),  # 48kHz
    0x17: (96000, 1920, "slin96"),  # 96kHz
    0x18: (192000, 3840, "slin192"),  # 192kHz
}

# Default: 16kHz, 16-bit signed linear, mono → 320 bytes = 20ms
AUDIOSOCKET_TYPE_AUDIO = 0x12  # slin16
AUDIO_SAMPLE_RATE = 16000
AUDIO_FRAME_SIZE = 320  # 20ms at 16kHz, 16-bit

# Error type
AUDIOSOCKET_TYPE_ERROR = 0xFF


@dataclass
class AudioSocketConnection:
    """Represents an active AudioSocket connection from Asterisk."""

    channel_uuid: str
    reader: asyncio.StreamReader
    writer: asyncio.StreamWriter
    peer: str
    connected: bool = True
    _disconnect_event: asyncio.Event = field(default_factory=asyncio.Event)

    async def send_audio(self, audio_data: bytes) -> bool:
        """Send an audio frame back to Asterisk."""
        if not self.connected:
            return False
        try:
            frame = (
                struct.pack("!BH", AUDIOSOCKET_TYPE_AUDIO, len(audio_data)) + audio_data
            )
            self.writer.write(frame)
            await self.writer.drain()
            return True
        except (ConnectionError, OSError) as e:
            logger.warning(f"[{self.channel_uuid}] Send error: {e}")
            self.connected = False
            self._disconnect_event.set()
            return False

    async def send_hangup(self):
        """Send a hangup frame to Asterisk."""
        if not self.connected:
            return
        try:
            frame = struct.pack("!BH", AUDIOSOCKET_TYPE_HANGUP, 0)
            self.writer.write(frame)
            await self.writer.drain()
        except (ConnectionError, OSError):
            pass

    async def send_dtmf(self, digit: str) -> bool:
        """Send a DTMF digit to Asterisk."""
        if not self.connected:
            return False
        try:
            # DTMF is 1-byte ASCII
            frame = struct.pack("!BH", AUDIOSOCKET_TYPE_DTMF, 1) + digit.encode("ascii")
            self.writer.write(frame)
            await self.writer.drain()
            return True
        except (ConnectionError, OSError) as e:
            logger.warning(f"[{self.channel_uuid}] DTMF send error: {e}")
            return False

    def get_sample_rate(self) -> int:
        """Get the sample rate for this connection (based on audio type)."""
        # Default to 16kHz if not set
        return AUDIO_SAMPLE_RATE

    def get_audio_frame_size(self) -> int:
        """Get the expected frame size for the sample rate."""
        return AUDIO_FRAME_SIZE

    async def wait_disconnected(self):
        """Wait until the connection is closed."""
        await self._disconnect_event.wait()

    def close(self):
        """Close the TCP connection."""
        self.connected = False
        self._disconnect_event.set()
        if not self.writer.is_closing():
            self.writer.close()


# Type for the callback invoked when a new AudioSocket connection arrives
OnConnectionCallback = Callable[[AudioSocketConnection], Awaitable[None]]


class AudioSocketServer:
    """Asyncio TCP server that accepts AudioSocket connections from Asterisk.

    For each incoming connection, it reads the UUID frame, creates an
    AudioSocketConnection, and invokes the on_connection callback.
    The callback is responsible for reading audio frames and managing
    the call lifecycle.
    """

    def __init__(
        self,
        host: str = "0.0.0.0",
        port: int = 9092,
        on_connection: Optional[OnConnectionCallback] = None,
    ):
        self._host = host
        self._port = port
        self._on_connection = on_connection
        self._server: Optional[asyncio.Server] = None
        self._connections: Dict[str, AudioSocketConnection] = {}

    @property
    def connections(self) -> Dict[str, AudioSocketConnection]:
        return self._connections

    async def start(self):
        """Start the TCP server."""
        self._server = await asyncio.start_server(
            self._handle_client, self._host, self._port
        )
        addr = self._server.sockets[0].getsockname()
        logger.info(f"AudioSocket server listening on {addr[0]}:{addr[1]}")

    async def stop(self):
        """Stop the server and close all connections."""
        for conn in list(self._connections.values()):
            conn.close()
        self._connections.clear()

        if self._server:
            self._server.close()
            await self._server.wait_closed()
            logger.info("AudioSocket server stopped")

    async def _handle_client(
        self, reader: asyncio.StreamReader, writer: asyncio.StreamWriter
    ):
        """Handle a new TCP connection from Asterisk."""
        peer = writer.get_extra_info("peername")
        peer_str = f"{peer[0]}:{peer[1]}" if peer else "unknown"
        logger.info(f"AudioSocket connection from {peer_str}")

        channel_uuid = None
        try:
            # First frame must be UUID (type 0x01)
            channel_uuid = await self._read_uuid(reader)
            if not channel_uuid:
                logger.warning(f"[{peer_str}] No UUID received, closing")
                writer.close()
                return

            conn = AudioSocketConnection(
                channel_uuid=channel_uuid,
                reader=reader,
                writer=writer,
                peer=peer_str,
            )
            self._connections[channel_uuid] = conn
            logger.info(f"[{channel_uuid}] AudioSocket established from {peer_str}")

            if self._on_connection:
                await self._on_connection(conn)

        except Exception as e:
            logger.error(f"[{channel_uuid or peer_str}] Handler error: {e}")
        finally:
            if channel_uuid and channel_uuid in self._connections:
                self._connections[channel_uuid].close()
                del self._connections[channel_uuid]
                logger.info(f"[{channel_uuid}] AudioSocket connection closed")

    async def _read_uuid(self, reader: asyncio.StreamReader) -> Optional[str]:
        """Read the initial UUID frame from AudioSocket."""
        header = await asyncio.wait_for(reader.readexactly(3), timeout=5.0)
        frame_type, length = struct.unpack("!BH", header)

        if frame_type != AUDIOSOCKET_TYPE_UUID:
            logger.warning(f"Expected UUID frame (0x01), got 0x{frame_type:02x}")
            return None

        payload = await reader.readexactly(length)
        return payload.decode("utf-8").strip()


async def read_audiosocket_frames(conn: AudioSocketConnection):
    """Generator that yields (frame_type, payload) from an AudioSocket connection.

    Usage:
        async for frame_type, payload in read_audiosocket_frames(conn):
            if frame_type == AUDIOSOCKET_TYPE_AUDIO:
                process_audio(payload)
            elif frame_type == AUDIOSOCKET_TYPE_HANGUP:
                break
    """
    reader = conn.reader
    try:
        while conn.connected:
            header = await reader.readexactly(3)
            frame_type, length = struct.unpack("!BH", header)

            if frame_type == AUDIOSOCKET_TYPE_HANGUP:
                logger.info(f"[{conn.channel_uuid}] Hangup received")
                conn.close()
                yield frame_type, b""
                return

            payload = await reader.readexactly(length) if length > 0 else b""
            yield frame_type, payload

    except asyncio.IncompleteReadError:
        logger.info(f"[{conn.channel_uuid}] Connection closed by Asterisk")
        conn.close()
    except (ConnectionError, OSError) as e:
        logger.warning(f"[{conn.channel_uuid}] Read error: {e}")
        conn.close()
