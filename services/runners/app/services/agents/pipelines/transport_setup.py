import logging
from pipecat.transports.daily.transport import DailyTransport, DailyParams
from pipecat.transports.livekit.transport import LiveKitTransport, LiveKitParams
from pipecat.audio.vad.vad_analyzer import VADParams
from app.schemas.agent import AgentConfig
from app.core.config import settings

logger = logging.getLogger(__name__)


async def setup_transport(
    room_url: str, token: str, room_name: str, config: AgentConfig, websocket=None
):
    """Configura el transporte Daily, LiveKit o FastAPI WebSocket con su VAD correspondiente."""
    provider = settings.DEFAULT_TRANSPORT_PROVIDER.lower()
    if (
        hasattr(config.runtime_profiles, "transport")
        and config.runtime_profiles.transport
    ):
        provider = config.runtime_profiles.transport.provider.lower()

    # FastAPI WebSocket (sin WebRTC)
    if provider == "websocket" or websocket is not None:
        return await setup_websocket_transport(config, websocket)

    # Configurar VAD
    vad_config = config.runtime_profiles.vad
    start_secs = max(
        0.4, vad_config.params.start_secs if vad_config and vad_config.params else 0.4
    )
    stop_secs = 0.2  # Optimizado para Smart Turn

    vad_params = VADParams(
        confidence=vad_config.params.confidence
        if vad_config and vad_config.params
        else 0.7,
        start_secs=start_secs,
        stop_secs=stop_secs,
        min_volume=vad_config.params.min_volume
        if vad_config and vad_config.params
        else 0.6,
    )

    vad_analyzer_provider = vad_config.provider.lower() if vad_config else "silero"
    if vad_analyzer_provider == "aic":
        from pipecat.audio.vad.aic_vad import AICVADAnalyzer

        vad_analyzer = AICVADAnalyzer(params=vad_params)
        logger.info("🎙️ Transport: Using AIC VAD")
    else:
        from pipecat.audio.vad.silero import SileroVADAnalyzer

        vad_analyzer = SileroVADAnalyzer(params=vad_params)
        logger.info("🎙️ Transport: Using Silero VAD")

    if provider == "daily":
        transport = DailyTransport(
            room_url,
            token,
            config.metadata.name,
            DailyParams(
                audio_out_enabled=True,
                audio_in_enabled=True,
                camera_out_enabled=False,
                vad_analyzer=vad_analyzer,
            ),
        )
    else:
        transport = LiveKitTransport(
            room_url,
            token,
            room_name,
            LiveKitParams(
                audio_out_enabled=True,
                audio_in_enabled=True,
                camera_out_enabled=False,
                vad_analyzer=vad_analyzer,
            ),
        )

    return transport, vad_analyzer


async def setup_websocket_transport(config: AgentConfig, websocket):
    """Configura el transporte FastAPI WebSocket para audio PCM directo."""
    from pipecat.transports.fastapi_websocket import (
        FastAPIWebsocketTransport,
        FastAPIWebsocketParams,
    )
    from pipecat.audio.vad.silero import SileroVADAnalyzer

    vad_config = config.runtime_profiles.vad
    vad_params = VADParams(
        confidence=vad_config.params.confidence
        if vad_config and vad_config.params
        else 0.7,
        start_secs=max(
            0.4,
            vad_config.params.start_secs if vad_config and vad_config.params else 0.4,
        ),
        stop_secs=0.2,
        min_volume=vad_config.params.min_volume
        if vad_config and vad_config.params
        else 0.6,
    )
    vad_analyzer = SileroVADAnalyzer(params=vad_params)

    sample_rate = 16000
    if hasattr(config.runtime_profiles, "tts") and config.runtime_profiles.tts:
        sample_rate = getattr(config.runtime_profiles.tts, "sample_rate", 16000)

    params = FastAPIWebsocketParams(
        audio_in_sample_rate=sample_rate,
        audio_out_sample_rate=sample_rate,
        add_wav_header=False,
    )

    transport = FastAPIWebsocketTransport(websocket=websocket, params=params)

    logger.info(f"🎙️ Transport: Using FastAPIWebsocketTransport ({sample_rate}Hz)")

    return transport, vad_analyzer


async def setup_websocket_transport(config: AgentConfig, websocket):
    """Configura el transporte WebSocket para audio PCM directo."""
    from app.services.sip.transport import (
        WebSocketClientTransport,
        WebSocketClientParams,
    )
    from pipecat.audio.vad.silero import SileroVADAnalyzer
    from pipecat.audio.vad.vad_analyzer import VADParams

    vad_config = config.runtime_profiles.vad
    vad_params = VADParams(
        confidence=vad_config.params.confidence
        if vad_config and vad_config.params
        else 0.7,
        start_secs=max(
            0.4,
            vad_config.params.start_secs if vad_config and vad_config.params else 0.4,
        ),
        stop_secs=0.2,
        min_volume=vad_config.params.min_volume
        if vad_config and vad_config.params
        else 0.6,
    )
    vad_analyzer = SileroVADAnalyzer(params=vad_params)

    sample_rate = 16000
    if hasattr(config.runtime_profiles, "tts") and config.runtime_profiles.tts:
        sample_rate = getattr(config.runtime_profiles.tts, "sample_rate", 16000)

    params = WebSocketClientParams(
        audio_in_sample_rate=sample_rate,
        audio_out_sample_rate=sample_rate,
    )

    transport = WebSocketClientTransport(
        params=params,
        websocket=websocket,
    )

    logger.info(f"🎙️ Transport: Using WebSocket direct ({sample_rate}Hz)")

    return transport, vad_analyzer
