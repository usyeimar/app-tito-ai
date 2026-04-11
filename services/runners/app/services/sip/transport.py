"""Custom Pipecat transports for Asterisk AudioSocket and WebSocket (chan_websocket).

Two transport implementations:
1. AudioSocket (TCP) - legacy, simpler protocol
2. WebSocket - Asterisk chan_websocket (Asterisk 20.18+, 22.8+, 23.2+)

Audio flows:
    Teléfono → Asterisk (SIP/RTP) → AudioSocket/WebSocket → This Transport → STT → LLM → TTS

Bridges SIP audio (via Asterisk AudioSocket TCP) directly into a Pipecat
pipeline, bypassing WebRTC entirely. Audio flows:

    Teléfono → Asterisk (SIP/RTP) → AudioSocket TCP → This Transport → STT → LLM → TTS
    Teléfono ← Asterisk (SIP/RTP) ← AudioSocket TCP ← This Transport ← TTS

Based on the same patterns as WebsocketServerTransport.
"""

import asyncio
import time
import logging
from typing import Optional

from pipecat.frames.frames import (
    CancelFrame,
    EndFrame,
    Frame,
    InputAudioRawFrame,
    InterruptionFrame,
    OutputAudioRawFrame,
    StartFrame,
)
from pipecat.processors.frame_processor import FrameDirection, FrameProcessor
from pipecat.transports.base_input import BaseInputTransport
from pipecat.transports.base_output import BaseOutputTransport
from pipecat.transports.base_transport import BaseTransport, TransportParams

from app.services.sip.audiosocket_server import (
    AudioSocketConnection,
    read_audiosocket_frames,
    AUDIOSOCKET_TYPE_AUDIO,
    AUDIOSOCKET_TYPE_HANGUP,
    AUDIO_SAMPLE_RATE,
)

logger = logging.getLogger(__name__)


class SIPAudioSocketParams(TransportParams):
    """Parameters for the SIP AudioSocket transport."""

    audio_in_sample_rate: Optional[int] = AUDIO_SAMPLE_RATE
    audio_out_sample_rate: Optional[int] = AUDIO_SAMPLE_RATE
    audio_in_channels: int = 1
    audio_out_channels: int = 1
    audio_in_enabled: bool = True
    audio_out_enabled: bool = True


class SIPAudioSocketInputTransport(BaseInputTransport):
    """Reads audio frames from an Asterisk AudioSocket TCP connection."""

    def __init__(
        self,
        transport: "SIPAudioSocketTransport",
        params: SIPAudioSocketParams,
        **kwargs,
    ):
        super().__init__(params, **kwargs)
        self._transport = transport
        self._params = params
        self._read_task: Optional[asyncio.Task] = None
        self._initialized = False

    async def start(self, frame: StartFrame):
        await super().start(frame)
        if self._initialized:
            return
        self._initialized = True
        await self.set_transport_ready(frame)

    def start_reading(self, conn: AudioSocketConnection):
        """Start reading audio from the AudioSocket connection."""
        if self._read_task and not self._read_task.done():
            self._read_task.cancel()
        self._read_task = self.create_task(self._read_audio_loop(conn))

    async def _read_audio_loop(self, conn: AudioSocketConnection):
        """Read audio frames from AudioSocket and push them into the pipeline."""
        try:
            async for frame_type, payload in read_audiosocket_frames(conn):
                if frame_type == AUDIOSOCKET_TYPE_AUDIO and payload:
                    audio_frame = InputAudioRawFrame(
                        audio=payload,
                        sample_rate=AUDIO_SAMPLE_RATE,
                        num_channels=1,
                    )
                    await self.push_audio_frame(audio_frame)
                elif frame_type == AUDIOSOCKET_TYPE_HANGUP:
                    break
        except asyncio.CancelledError:
            raise
        except Exception as e:
            logger.error(f"AudioSocket read error: {e}")

    async def stop(self, frame: EndFrame):
        await super().stop(frame)
        if self._read_task and not self._read_task.done():
            self._read_task.cancel()
            try:
                await self._read_task
            except asyncio.CancelledError:
                pass
        self._read_task = None

    async def cancel(self, frame: CancelFrame):
        await super().cancel(frame)
        if self._read_task and not self._read_task.done():
            self._read_task.cancel()
        self._read_task = None

    async def cleanup(self):
        await super().cleanup()
        await self._transport.cleanup()


class SIPAudioSocketOutputTransport(BaseOutputTransport):
    """Writes audio frames to an Asterisk AudioSocket TCP connection."""

    def __init__(
        self,
        transport: "SIPAudioSocketTransport",
        params: SIPAudioSocketParams,
        **kwargs,
    ):
        super().__init__(params, **kwargs)
        self._transport = transport
        self._params = params
        self._conn: Optional[AudioSocketConnection] = None
        self._send_interval = 0
        self._next_send_time = 0
        self._initialized = False

    def set_connection(self, conn: AudioSocketConnection):
        self._conn = conn

    async def start(self, frame: StartFrame):
        await super().start(frame)
        if self._initialized:
            return
        self._initialized = True
        self._send_interval = (self.audio_chunk_size / self.sample_rate) / 2
        await self.set_transport_ready(frame)

    async def stop(self, frame: EndFrame):
        await super().stop(frame)
        if self._conn:
            await self._conn.send_hangup()

    async def cancel(self, frame: CancelFrame):
        await super().cancel(frame)

    async def cleanup(self):
        await super().cleanup()
        await self._transport.cleanup()

    async def process_frame(self, frame: Frame, direction: FrameDirection):
        await super().process_frame(frame, direction)
        if isinstance(frame, InterruptionFrame):
            self._next_send_time = 0

    async def write_audio_frame(self, frame: OutputAudioRawFrame) -> bool:
        """Write TTS audio back to Asterisk via AudioSocket."""
        if not self._conn or not self._conn.connected:
            return False

        success = await self._conn.send_audio(frame.audio)

        # Throttle to simulate audio device timing
        current_time = time.monotonic()
        sleep_duration = max(0, self._next_send_time - current_time)
        await asyncio.sleep(sleep_duration)
        if sleep_duration == 0:
            self._next_send_time = time.monotonic() + self._send_interval
        else:
            self._next_send_time += self._send_interval

        return success


class SIPAudioSocketTransport(BaseTransport):
    """Pipecat transport that bridges Asterisk AudioSocket to a pipeline.

    Usage:
        transport = SIPAudioSocketTransport(
            params=SIPAudioSocketParams(vad_analyzer=silero_vad),
            conn=audiosocket_connection,
        )

        pipeline = Pipeline([
            transport.input(),
            stt,
            context_aggregator.user(),
            llm,
            tts,
            transport.output(),
            context_aggregator.assistant(),
        ])

    Event handlers:
        - on_sip_connected(transport, channel_uuid): AudioSocket connection established
        - on_sip_disconnected(transport, channel_uuid): Connection closed / hangup
        - on_dtmf_received(transport, digit): DTMF tone received (via AMI, not AudioSocket)
    """

    def __init__(
        self,
        params: SIPAudioSocketParams,
        conn: AudioSocketConnection,
        input_name: Optional[str] = None,
        output_name: Optional[str] = None,
    ):
        super().__init__(input_name=input_name, output_name=output_name)
        self._params = params
        self._conn = conn
        self._input: Optional[SIPAudioSocketInputTransport] = None
        self._output: Optional[SIPAudioSocketOutputTransport] = None
        self._disconnect_event = asyncio.Event()

        # Register event handlers
        self._register_event_handler("on_sip_connected")
        self._register_event_handler("on_sip_disconnected")
        self._register_event_handler("on_dtmf_received")
        self._register_event_handler("on_first_participant_joined")
        self._register_event_handler("on_bot_started_speaking")

        # Monitor connection
        self._monitor_task: Optional[asyncio.Task] = None

    @property
    def channel_uuid(self) -> str:
        return self._conn.channel_uuid

    def input(self) -> SIPAudioSocketInputTransport:
        if not self._input:
            self._input = SIPAudioSocketInputTransport(
                self, self._params, name=self._input_name
            )
        return self._input

    def output(self) -> SIPAudioSocketOutputTransport:
        if not self._output:
            self._output = SIPAudioSocketOutputTransport(
                self, self._params, name=self._output_name
            )
            self._output.set_connection(self._conn)
        return self._output

    async def start(self):
        """Start the transport and begin reading audio."""
        # Start reading audio from AudioSocket
        if self._input:
            self._input.start_reading(self._conn)

        # Start connection monitor
        self._monitor_task = asyncio.create_task(self._monitor_connection())

        # Fire connected event
        await self._call_event_handler("on_sip_connected", self._conn.channel_uuid)
        await self._call_event_handler(
            "on_first_participant_joined", {"id": self._conn.channel_uuid}
        )

    async def _monitor_connection(self):
        """Monitor the AudioSocket connection and fire disconnect event on close."""
        try:
            await self._conn.wait_disconnected()
        except asyncio.CancelledError:
            raise
        finally:
            self._disconnect_event.set()
            await self._call_event_handler(
                "on_sip_disconnected", self._conn.channel_uuid
            )

    async def wait_disconnected(self):
        """Wait until the SIP call is disconnected (hangup)."""
        await self._disconnect_event.wait()

    async def hangup(self):
        """Programmatically hang up the call."""
        if self._conn:
            await self._conn.send_hangup()
            self._conn.close()

    async def inject_dtmf(self, digit: str):
        """Inject a DTMF event (called by AMI controller when it detects DTMF)."""
        await self._call_event_handler("on_dtmf_received", digit)

    async def cleanup(self):
        """Clean up resources."""
        if self._monitor_task and not self._monitor_task.done():
            self._monitor_task.cancel()
            try:
                await self._monitor_task
            except asyncio.CancelledError:
                pass
        if self._conn:
            self._conn.close()


# ============================================================================
# WebSocket Transport (Asterisk chan_websocket)
# ============================================================================

import websockets

from app.services.sip.websocket_server import (
    WebSocketConnection as WSConnection,
)


class SIPWebSocketParams(TransportParams):
    """Parameters for the SIP WebSocket transport (chan_websocket)."""

    audio_in_sample_rate: Optional[int] = 8000
    audio_out_sample_rate: Optional[int] = 8000
    audio_in_channels: int = 1
    audio_out_channels: int = 1
    audio_in_enabled: bool = True
    audio_out_enabled: bool = True


class SIPWebSocketInputTransport(BaseInputTransport):
    """Reads audio frames from Asterisk WebSocket (chan_websocket)."""

    def __init__(
        self, transport: "SIPWebSocketTransport", params: SIPWebSocketParams, **kwargs
    ):
        super().__init__(params, **kwargs)
        self._transport = transport
        self._params = params
        self._read_task: Optional[asyncio.Task] = None
        self._initialized = False

    async def start(self, frame: StartFrame):
        await super().start(frame)
        if self._initialized:
            return
        self._initialized = True
        await self.set_transport_ready(frame)

    def start_reading(self, conn: WSConnection):
        """Start reading audio from the WebSocket connection."""
        if self._read_task and not self._read_task.done():
            self._read_task.cancel()
        self._read_task = self.create_task(self._read_websocket_audio(conn))

    async def _read_websocket_audio(self, conn: WSConnection):
        """Read audio frames from WebSocket and push into pipeline."""
        try:
            # Audio comes as binary frames - handled in main loop
            pass
        except asyncio.CancelledError:
            raise
        except Exception as e:
            logger.error(f"WebSocket read error: {e}")

    async def stop(self, frame: EndFrame):
        await super().stop(frame)
        if self._read_task and not self._read_task.done():
            self._read_task.cancel()
            try:
                await self._read_task
            except asyncio.CancelledError:
                pass
        self._read_task = None

    async def cancel(self, frame: CancelFrame):
        await super().cancel(frame)
        if self._read_task and not self._read_task.done():
            self._read_task.cancel()
        self._read_task = None

    async def cleanup(self):
        await super().cleanup()
        await self._transport.cleanup()


class SIPWebSocketOutputTransport(BaseOutputTransport):
    """Writes audio frames to Asterisk WebSocket (chan_websocket)."""

    def __init__(
        self, transport: "SIPWebSocketTransport", params: SIPWebSocketParams, **kwargs
    ):
        super().__init__(params, **kwargs)
        self._transport = transport
        self._params = params
        self._conn: Optional[WSConnection] = None
        self._initialized = False

    def set_connection(self, conn: WSConnection):
        self._conn = conn

    async def start(self, frame: StartFrame):
        await super().start(frame)
        if self._initialized:
            return
        self._initialized = True
        await self.set_transport_ready(frame)

    async def stop(self, frame: EndFrame):
        await super().stop(frame)
        if self._conn:
            await self._conn.hangup()

    async def cancel(self, frame: CancelFrame):
        await super().cancel(frame)

    async def process_frame(self, frame: Frame, direction: FrameDirection):
        await super().process_frame(frame, direction)
        if isinstance(frame, InterruptionFrame):
            await self._conn.pause_media()

    async def write_audio_frame(self, frame: OutputAudioRawFrame) -> bool:
        """Write TTS audio back to Asterisk via WebSocket."""
        if not self._conn or not self._conn.connected:
            return False

        return await self._conn.send_audio(frame.audio)


class SIPWebSocketTransport(BaseTransport):
    """Pipecat transport that bridges Asterisk chan_websocket to a pipeline.

    Usage:
        transport = SIPWebSocketTransport(
            params=SIPWebSocketParams(vad_analyzer=silero_vad),
            conn=websocket_connection,
        )

        pipeline = Pipeline([
            transport.input(),
            stt,
            context_aggregator.user(),
            llm,
            tts,
            transport.output(),
            context_aggregator.assistant(),
        ])

    Event handlers (same as AudioSocket):
        - on_sip_connected(transport, connection_id): WebSocket connection established
        - on_sip_disconnected(transport, connection_id): Connection closed / hangup
        - on_dtmf_received(transport, digit): DTMF tone received
    """

    def __init__(
        self,
        params: SIPWebSocketParams,
        conn: WSConnection,
        input_name: Optional[str] = None,
        output_name: Optional[str] = None,
    ):
        super().__init__(input_name=input_name, output_name=output_name)
        self._params = params
        self._conn = conn
        self._input: Optional[SIPWebSocketInputTransport] = None
        self._output: Optional[SIPWebSocketOutputTransport] = None
        self._disconnect_event = asyncio.Event()

        self._register_event_handler("on_sip_connected")
        self._register_event_handler("on_sip_disconnected")
        self._register_event_handler("on_dtmf_received")
        self._register_event_handler("on_first_participant_joined")
        self._register_event_handler("on_bot_started_speaking")

        self._monitor_task: Optional[asyncio.Task] = None

    @property
    def connection_id(self) -> str:
        return self._conn.connection_id

    def input(self) -> SIPWebSocketInputTransport:
        if not self._input:
            self._input = SIPWebSocketInputTransport(
                self, self._params, name=self._input_name
            )
        return self._input

    def output(self) -> SIPWebSocketOutputTransport:
        if not self._output:
            self._output = SIPWebSocketOutputTransport(
                self, self._params, name=self._output_name
            )
            self._output.set_connection(self._conn)
        return self._output

    async def start(self):
        """Start the transport and begin reading audio."""
        if self._input:
            self._input.start_reading(self._conn)

        self._monitor_task = asyncio.create_task(self._monitor_connection())

        await self._call_event_handler("on_sip_connected", self._conn.connection_id)
        await self._call_event_handler(
            "on_first_participant_joined", {"id": self._conn.connection_id}
        )

    async def _monitor_connection(self):
        """Monitor the WebSocket connection and fire disconnect event."""
        try:
            await self._conn.wait_media_start()
        except asyncio.CancelledError:
            raise
        finally:
            self._disconnect_event.set()
            await self._call_event_handler(
                "on_sip_disconnected", self._conn.connection_id
            )

    async def wait_disconnected(self):
        """Wait until the WebSocket connection is closed."""
        await self._disconnect_event.wait()

    async def hangup(self):
        """Programmatically hang up the call."""
        if self._conn:
            await self._conn.hangup()
            self._conn.close()

    async def inject_dtmf(self, digit: str):
        """Inject a DTMF event."""
        await self._call_event_handler("on_dtmf_received", digit)

    async def cleanup(self):
        """Clean up resources."""
        if self._monitor_task and not self._monitor_task.done():
            self._monitor_task.cancel()
            try:
                await self._monitor_task
            except asyncio.CancelledError:
                pass
        if self._conn:
            self._conn.close()


# ============================================================================
# WebSocket Client Transport (para /sessions/{id}/audio endpoint)
# ============================================================================


class WebSocketClientParams(TransportParams):
    """Parameters for WebSocket client transport."""

    audio_in_sample_rate: Optional[int] = 16000
    audio_out_sample_rate: Optional[int] = 16000
    audio_in_channels: int = 1
    audio_out_channels: int = 1
    audio_in_enabled: bool = True
    audio_out_enabled: bool = True


class WebSocketClientTransport(BaseTransport):
    """Pipecat transport para WebSocket endpoint /sessions/{id}/audio.

    Conecta audio PCM bidireccional desde WebSocket del cliente
    al pipeline de Pipecat.
    """

    def __init__(
        self,
        params: WebSocketClientParams,
        websocket,
        input_name: Optional[str] = None,
        output_name: Optional[str] = None,
    ):
        super().__init__(input_name=input_name, output_name=output_name)
        self._params = params
        self._ws = websocket
        self._input: Optional[WebSocketClientInputTransport] = None
        self._output: Optional[WebSocketClientOutputTransport] = None
        self._disconnect_event = asyncio.Event()
        self._sample_rate = params.audio_in_sample_rate or 16000
        self._running = False

        self._register_event_handler("on_sip_connected")
        self._register_event_handler("on_sip_disconnected")
        self._register_event_handler("on_first_participant_joined")
        self._register_event_handler("on_bot_started_speaking")

    def input(self) -> "WebSocketClientInputTransport":
        if not self._input:
            self._input = WebSocketClientInputTransport(
                self, self._params, name=self._input_name
            )
        return self._input

    def output(self) -> "WebSocketClientOutputTransport":
        if not self._output:
            self._output = WebSocketClientOutputTransport(
                self, self._params, name=self._output_name
            )
            self._output.set_websocket(self._ws)
        return self._output

    async def start(self):
        """Start transport."""
        self._running = True
        await self._call_event_handler("on_sip_connected", "websocket-client")
        await self._call_event_handler(
            "on_first_participant_joined", {"id": "websocket-client"}
        )

    async def wait_disconnected(self):
        await self._disconnect_event.wait()

    async def cleanup(self):
        self._running = False
        self._disconnect_event.set()


class WebSocketClientInputTransport(BaseInputTransport):
    """Lee audio PCM del WebSocket del cliente."""

    def __init__(
        self,
        transport: WebSocketClientTransport,
        params: WebSocketClientParams,
        **kwargs,
    ):
        super().__init__(params, **kwargs)
        self._transport = transport
        self._params = params
        self._ws = transport._ws
        self._read_task: Optional[asyncio.Task] = None
        self._initialized = False

    async def start(self, frame: StartFrame):
        await super().start(frame)
        if self._initialized:
            return
        self._initialized = True
        await self.set_transport_ready(frame)

    async def stop(self, frame: EndFrame):
        await super().stop(frame)
        if self._read_task:
            self._read_task.cancel()
            self._read_task = None

    async def cleanup(self):
        await super().cleanup()

    async def recv_audio(self) -> Optional[bytes]:
        """Receive binary audio from WebSocket."""
        try:
            msg = await asyncio.wait_for(self._ws.receive(), timeout=1.0)
            if msg["type"] == "binary":
                return msg["bytes"]
            elif msg["type"] == "close":
                return None
        except asyncio.TimeoutError:
            return None
        except Exception:
            return None
        return None

    def start_reading(self):
        """Start reading audio loop."""
        if self._read_task:
            return
        self._read_task = asyncio.create_task(self._read_loop())

    async def _read_loop(self):
        """Read audio continuously from WebSocket."""
        try:
            while self._transport._running:
                audio = await self.recv_audio()
                if audio:
                    frame = InputAudioRawFrame(
                        audio=audio,
                        sample_rate=self._params.audio_in_sample_rate or 16000,
                        num_channels=1,
                    )
                    await self.push_audio_frame(frame)
                await asyncio.sleep(0.01)
        except asyncio.CancelledError:
            pass
        except Exception as e:
            logger.error(f"WebSocket read error: {e}")


class WebSocketClientOutputTransport(BaseOutputTransport):
    """Escribe audio PCM hacia el WebSocket del cliente."""

    def __init__(
        self,
        transport: WebSocketClientTransport,
        params: WebSocketClientParams,
        **kwargs,
    ):
        super().__init__(params, **kwargs)
        self._transport = transport
        self._params = params
        self._ws = None
        self._initialized = False

    def set_websocket(self, ws):
        self._ws = ws

    async def start(self, frame: StartFrame):
        await super().start(frame)
        if self._initialized:
            return
        self._initialized = True
        await self.set_transport_ready(frame)

    async def stop(self, frame: EndFrame):
        await super().stop(frame)

    async def write_audio_frame(self, frame: OutputAudioRawFrame) -> bool:
        """Write TTS audio to WebSocket as binary."""
        if not self._ws or not self._running:
            return False
        try:
            await self._ws.send(frame.audio, binary=True)
            return True
        except Exception as e:
            logger.warning(f"WebSocket send error: {e}")
            return False


SIPTransport = SIPAudioSocketTransport
