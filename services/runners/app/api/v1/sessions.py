import asyncio
import json
import logging
import time
import uuid
import pipecat

from fastapi import (
    APIRouter,
    HTTPException,
    Query,
    WebSocket,
    WebSocketDisconnect,
    status,
    Request,
)

from app.core.config import settings
from app.schemas.agent import AgentConfig
from app.schemas.sessions import (
    SessionResponse,
    SessionContext,
    ActionResponse,
    SessionListResponse,
    SessionLink,
)
from app.schemas.errors import APIErrorResponse
from app.services.livekit_service import LiveKitService
from app.services.daily_service import DailyService
from app.services.session_manager import session_manager
from app.services.task_manager import task_manager
from app.services.agents.pipelines.agent_pipeline_engine import AgentPipelineEngine
from app.services.webhook_service import WebhookService

logger = logging.getLogger(__name__)

router = APIRouter()


def get_session_links(
    request: Request, session_id: str, ws_url: str = None, playground_url: str = None
):
    """Genera enlaces HATEOAS para una sesión."""
    base_url = str(request.base_url).rstrip("/")
    ws_protocol = "wss" if request.url.scheme == "https" else "ws"
    base_ws = f"{ws_protocol}://{request.url.netloc}/api/v1/sessions/{session_id}"

    api_path = f"{base_url}/api/v1/sessions/{session_id}"

    links = {
        "self": SessionLink(href=api_path, method="GET"),
        "stop": SessionLink(href=api_path, method="DELETE"),
        "transcript": SessionLink(href=f"{base_ws}/transcript", method="GET"),
        "chat": SessionLink(href=f"{base_ws}/chat", method="GET"),
        "audio": SessionLink(href=f"{base_ws}/audio", method="GET"),
    }

    if ws_url:
        links["preview"] = SessionLink(href=ws_url, method="GET")
    if playground_url:
        links["playground"] = SessionLink(href=playground_url, method="GET")

    return links


@router.post(
    "/",
    status_code=status.HTTP_201_CREATED,
    response_model=SessionResponse,
    response_model_exclude_none=True,
    summary="Crear Sesion de Agente de Voz",
    response_description="Sesion creada exitosamente con credenciales de conexion",
    responses={
        201: {"model": SessionResponse, "description": "Sesion creada exitosamente."},
        503: {
            "model": APIErrorResponse,
            "description": "Runner al maximo de capacidad.",
        },
    },
)
async def create_session(config: AgentConfig, request: Request):
    """
    Crea una nueva sesion de agente de voz en tiempo real.

    **Que hace este endpoint:**

    1. Verifica que el runner tenga capacidad disponible.
    2. Crea una sala WebRTC en el proveedor configurado (Daily.co o LiveKit).
    3. Genera tokens de acceso para el usuario y el bot.
    4. Lanza el pipeline de IA en background: **STT → LLM → TTS**.
    5. Devuelve las credenciales y enlaces HATEOAS para la conexion.

    **Como usar la respuesta:**

    - Usa `url` + `access_token` para unirte a la sala desde tu frontend (Daily SDK o LiveKit SDK).
    - Conecta al WebSocket indicado en `_links.ws` para recibir transcripciones en tiempo real.
    - El agente empezara a hablar automaticamente si `behavior.initial_action` es `SPEAK_FIRST`.

    **Proveedores de transporte:**

    - Si `runtime_profiles.transport.provider` no se especifica, se usa el default del server (`DEFAULT_TRANSPORT_PROVIDER`).
    - `daily` → Crea sala en Daily.co, devuelve URL HTTPS.
    - `livekit` → Crea sala en LiveKit, devuelve URL WSS.
    """
    if task_manager.count() >= settings.MAX_CONCURRENT_SESSIONS:
        logger.warning(
            f"Runner at capacity: {task_manager.count()}/{settings.MAX_CONCURRENT_SESSIONS}"
        )
        raise HTTPException(
            status_code=503,
            detail="Runner at capacity",
            headers={"Retry-After": "30"},
        )

    try:
        # Determinar proveedor
        provider = settings.DEFAULT_TRANSPORT_PROVIDER.lower()
        if (
            hasattr(config.runtime_profiles, "transport")
            and config.runtime_profiles.transport
        ):
            provider = config.runtime_profiles.transport.provider.lower()

        session_id = f"sess_{uuid.uuid4().hex[:12]}"
        room_name = f"tito_{config.agent_id}_{uuid.uuid4().hex[:8]}"
        participant_name = f"user_{uuid.uuid4().hex[:6]}"

        # Provider "websocket": no se levanta room ni pipeline ahora.
        # El pipeline se construye on-demand cuando el cliente conecta a /audio.
        if provider == "websocket":
            await session_manager.save_session(
                session_id, config, room_name=session_id, provider=provider
            )
            ws_protocol = "wss" if request.url.scheme == "https" else "ws"
            audio_ws_url = (
                f"{ws_protocol}://{request.url.netloc}"
                f"/api/v1/sessions/{session_id}/audio"
            )
            logger.info(f"🚀 Sesión [{session_id}] iniciada en [websocket]")
            return SessionResponse(
                session_id=session_id,
                room_name=session_id,
                provider="websocket",
                ws_url=audio_ws_url,
                context=SessionContext(
                    agent_id=config.agent_id,
                    tenant_id=config.tenant_id,
                    created_at=time.time(),
                    expires_at=time.time() + 3600,
                ),
                _links=get_session_links(request, session_id),
            )

        # 1. Crear room en proveedor de transporte (síncrono, falla rápido)
        if provider == "daily":
            room_data = await DailyService.create_room_and_tokens(
                room_name, participant_name
            )
        else:
            room_data = LiveKitService.create_room_and_tokens(
                room_name, participant_name
            )

        # 2. Guardar metadata de configuración
        await session_manager.save_session(
            session_id, config, room_name=room_name, provider=provider
        )

        # 3. Lanzar pipeline en background CON referencia controlada
        task = asyncio.create_task(
            _run_session(session_id, room_data, config, provider),
            name=f"session-{session_id}",
        )
        await task_manager.add(session_id, task)

        logger.info(f"🚀 Sesión [{session_id}] iniciada en [{provider}]")

        # 4. Preparar respuesta con HATEOAS
        expiration = time.time() + 3600

        ws_url = room_data["ws_url"]
        playground_url = f"https://livekit.io/playground?room={room_data['room_name']}&token={room_data['user_token']}"

        links = get_session_links(
            request, session_id, ws_url=ws_url, playground_url=playground_url
        )

        return SessionResponse(
            session_id=session_id,
            room_name=room_data["room_name"],
            provider=provider,
            ws_url=ws_url,
            playground_url=playground_url,
            access_token=room_data["user_token"],
            context=SessionContext(
                agent_id=config.agent_id,
                tenant_id=config.tenant_id,
                created_at=time.time(),
                expires_at=expiration,
            ),
            _links=links,
        )
    except Exception as e:
        logger.error(f"❌ Error al crear sesión: {e}")
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"No se pudo inicializar la sesión del agente: {str(e)}",
        )


async def _run_session(
    session_id: str, room_data: dict, config: AgentConfig, provider: str
):
    """Wrapper que asegura cleanup aunque el pipeline falle."""
    try:
        engine = AgentPipelineEngine(
            room_url=room_data["ws_url"],
            token=room_data["bot_token"],
            config=config,
            room_name=room_data["room_name"],
        )
        # Sincronizar session_id si AgentPipelineEngine genera uno propio (actualmente lo hace)
        engine.session_id = session_id
        await engine.run()
    except asyncio.CancelledError:
        logger.info(f"session_cancelled | session_id: {session_id}")
        raise
    except Exception as e:
        logger.error(f"session_error | session_id: {session_id} | error: {e}")
        # En el futuro usar emit_event_async
        try:
            await WebhookService.emit_event(
                config.tenant_id,
                config.agent_id,
                "session.error",
                room_data["ws_url"],
                {"session_id": session_id, "error": str(e)},
                override_url=config.callback_url,
            )
        except:
            pass
    finally:
        await task_manager.remove(session_id)
        await session_manager.delete_session(session_id)
        logger.info(f"session_cleanup_complete | session_id: {session_id}")


@router.get(
    "/",
    tags=["Sessions"],
    response_model=SessionListResponse,
    summary="Listar Sesiones Activas",
    response_description="Lista de sesiones activas en este runner",
)
async def list_active_sessions(request: Request):
    """
    Lista las sesiones de voz activas en este runner.

    Devuelve el conteo de sesiones activas y el estado operativo del runner.
    Util para dashboards de monitoreo y balanceo de carga.
    """
    sessions = await session_manager.list_sessions()
    active_count = task_manager.count()

    base_url = str(request.base_url).rstrip("/")
    links = {
        "self": SessionLink(href=f"{base_url}/api/v1/sessions", method="GET"),
        "create": SessionLink(href=f"{base_url}/api/v1/sessions", method="POST"),
    }

    return SessionListResponse(
        sessions=sessions, count=active_count, status="OPERATIONAL", _links=links
    )


@router.delete(
    "/{session_id}",
    tags=["Sessions"],
    response_model=ActionResponse,
    summary="Terminar Sesion de Agente",
    response_description="Confirmacion de que la sesion fue terminada",
    responses={
        200: {
            "content": {
                "application/json": {
                    "example": {
                        "success": True,
                        "message": "Sesion sess_a1b2c3d4e5f6 terminada exitosamente.",
                    }
                }
            }
        },
        404: {
            "description": "Sesion no encontrada",
            "content": {
                "application/json": {"example": {"detail": "Session not found"}}
            },
        },
    },
)
async def stop_session(session_id: str):
    """
    Termina una sesion de agente de voz de forma inmediata.

    **Que hace este endpoint:**

    1. Cancela el pipeline de Pipecat (STT/LLM/TTS) en este pod.
    2. Publica un comando `stop` via Redis Pub/Sub para otros pods.
    3. Elimina la sala WebRTC en el proveedor (Daily/LiveKit).
    4. Limpia los metadatos de sesion en Redis.

    **Cuando usarlo:**

    - El usuario cierra la llamada desde el frontend.
    - Un administrador necesita forzar el cierre de una sesion.
    - Timeout o error detectado desde el backend.
    """
    session = await session_manager.get_session(session_id)
    if not session:
        raise HTTPException(status_code=404, detail="Session not found")

    # 1. Cancelar el pipeline localmente (si existe en este pod)
    await task_manager.stop(session_id)

    # 2. Notificar vía Redis para otros pods (Opción C del plan)
    import json

    await session_manager._redis.publish(
        f"session:{session_id}:control", json.dumps({"action": "stop"})
    )

    # 3. Eliminar room en el proveedor
    try:
        room_name = session.get("room_name")
        provider = session.get("provider", "livekit")
        if provider == "daily":
            await DailyService.delete_room(room_name)
        else:
            await LiveKitService.delete_room(room_name)
    except Exception as e:
        logger.warning(f"delete_room_failed | session_id: {session_id} | error: {e}")

    return ActionResponse(
        success=True, message=f"Sesión {session_id} terminada exitosamente."
    )


@router.websocket("/{session_id}/transcript")
async def session_websocket(session_id: str, ws: WebSocket):
    """WebSocket endpoint para transcripciones en tiempo real.

    Recibe transcripciones del pipeline via Redis Pub/Sub y las envíe al cliente.
    El pipeline envía eventos usando session_manager.broadcast_transcript().

    El cliente recibe JSON:
    {"type": "transcript", "role": "user"|"assistant", "content": "...", "timestamp": "..."}
    """
    session = await session_manager.get_session(session_id)
    if not session:
        await ws.close(code=4004, reason="Session not found")
        return

    await ws.accept()

    asyncio.create_task(session_manager.subscribe_to_transcripts(session_id, ws))

    try:
        while True:
            await ws.receive_text()
    except WebSocketDisconnect:
        pass
    finally:
        await session_manager.unsubscribe_from_transcripts(session_id, ws)


@router.websocket("/{session_id}/chat")
async def session_chat_websocket(session_id: str, ws: WebSocket):
    """WebSocket endpoint para chat de texto bidireccional.

    Permite enviar y recibir mensajes de texto durante la llamada de voz.
    Útil para interfaces híbridas voz+texto o debugging.

    Mensajes del cliente (JSON):
        {"type": "message", "content": "texto del usuario"}

    Mensajes del servidor (JSON):
        {"type": "transcript", "role": "user|assistant", "content": "texto", "timestamp": 1234567890}
        {"type": "message", "content": "texto del agente"}
        {"type": "status", "state": "listening|thinking|speaking"}
    """
    logger.info(f"[WS chat] session_id={session_id}")
    session = await session_manager.get_session(session_id)
    logger.info(f"[WS chat] session found: {session is not None}")
    if not session:
        logger.warning(f"[WS chat] Session not found: {session_id}")
        await ws.close(code=4004, reason="Session not found")
        return

    await session_manager.connect_ws(session_id, ws)

    try:
        while True:
            try:
                data = await ws.receive_json()
            except Exception:
                raw = await ws.receive_text()
                logger.warning(f"[{session_id}] Invalid JSON received: {raw[:50]}")
                await ws.send_json(
                    {
                        "type": "error",
                        "message": 'Invalid JSON. Use: {"type": "message", "content": "..."}',
                    }
                )
                continue

            msg_type = data.get("type")

            if msg_type == "message":
                content = data.get("content", "")
                logger.info(f"[{session_id}] Chat message: {content[:100]}")

                await ws.send_json(
                    {
                        "type": "status",
                        "state": "processing",
                    }
                )

                try:
                    await session_manager._redis.publish(
                        f"session:{session_id}:chat",
                        json.dumps({"type": "message", "content": content}),
                    )
                except Exception as e:
                    logger.error(f"[{session_id}] Failed to publish chat: {e}")
                    await ws.send_json(
                        {"type": "error", "message": "Pipeline no disponible"}
                    )

            elif msg_type == "typing":
                logger.debug(f"[{session_id}] User typing")

    except WebSocketDisconnect:
        logger.info(f"[{session_id}] Chat disconnected")
    except Exception as e:
        logger.error(f"[{session_id}] Chat error: {e}")
    finally:
        session_manager.disconnect_ws(session_id, ws)


@router.websocket("/{session_id}/audio")
async def session_audio_websocket(session_id: str, ws: WebSocket):
    """WebSocket endpoint para audio PCM bidireccional con pipeline Pipecat.

    Usa FastAPIWebsocketTransport para correr el pipeline completo:
    CLIENTE → WebSocket → STT → LLM → TTS → WebSocket → CLIENTE
    """
    logger.info(f"[WS audio] session_id={session_id}")
    session = await session_manager.get_session(session_id)
    logger.info(f"[WS audio] session found: {session is not None}")
    if not session:
        logger.warning(f"[WS audio] Session not found: {session_id}")
        await ws.close(code=4004, reason="Session not found")
        return

    if session.get("provider") != "websocket":
        logger.warning(
            f"[WS audio] Session {session_id} provider={session.get('provider')}, expected 'websocket'"
        )
        await ws.close(code=4003, reason="Session is not a websocket-audio session")
        return

    await ws.accept()

    # Si hay un handler colgado de un intento previo, libéralo antes de re-registrar.
    if task_manager.get_task(session_id) is not None:
        await task_manager.remove(session_id)
    await task_manager.add(session_id, asyncio.current_task())
    try:
        config = AgentConfig.model_validate_json(session["config"])
        config.agent_id = session.get("agent_id", "default")

        from pipecat.transports.network.fastapi_websocket import (
            FastAPIWebsocketTransport,
            FastAPIWebsocketParams,
        )
        from pipecat.audio.vad.silero import SileroVADAnalyzer
        from pipecat.audio.vad.vad_analyzer import VADParams
        from pipecat.serializers.base_serializer import FrameSerializer
        from pipecat.frames.frames import (
            Frame,
            InputAudioRawFrame,
            OutputAudioRawFrame,
        )

        sample_rate = 16000
        vad_params = VADParams(
            confidence=0.7,
            start_secs=0.4,
            stop_secs=0.2,
            min_volume=0.6,
        )
        vad_analyzer = SileroVADAnalyzer(params=vad_params)

        class RawPCMSerializer(FrameSerializer):
            """Pasa audio PCM int16 mono crudo en ambos sentidos.

            FastAPIWebsocketTransport descarta audio si serializer is None
            (pipecat 0.0.108 fastapi.py L303-304 / L493-494).
            """

            def __init__(self, sr: int):
                super().__init__()
                self._sr = sr

            async def serialize(self, frame: Frame):
                if isinstance(frame, OutputAudioRawFrame):
                    return frame.audio
                return None

            async def deserialize(self, data):
                if isinstance(data, (bytes, bytearray)):
                    return InputAudioRawFrame(
                        audio=bytes(data),
                        sample_rate=self._sr,
                        num_channels=1,
                    )
                return None

        params = FastAPIWebsocketParams(
            audio_in_enabled=True,
            audio_out_enabled=True,
            audio_in_sample_rate=sample_rate,
            audio_out_sample_rate=sample_rate,
            vad_enabled=True,
            vad_analyzer=vad_analyzer,
            add_wav_header=False,
            serializer=RawPCMSerializer(sample_rate),
        )

        transport = FastAPIWebsocketTransport(websocket=ws, params=params)

        from app.services.agents.pipelines.context_setup import setup_context
        from app.services.agents.factory.builder import ServiceFactory

        stt = ServiceFactory.create_stt_service(config)
        llm = ServiceFactory.create_llm_service(config)
        tts = ServiceFactory.create_tts_service(config)
        _llm_context, context_aggregator = setup_context(session_id, config, vad_analyzer)

        from app.services.agents.pipelines.pipeline_builder import build_pipeline

        pipeline, _ = build_pipeline(
            transport=transport,
            stt=stt,
            llm=llm,
            tts=tts,
            context_aggregator=context_aggregator,
            config=config,
        )

        from pipecat.pipeline.task import PipelineTask, PipelineParams
        from pipecat.frames.frames import LLMRunFrame

        task = PipelineTask(
            pipeline, params=PipelineParams(allow_interruptions_by_all=True)
        )

        @transport.event_handler("on_client_connected")
        async def on_client_connected(transport, ws):
            logger.info(f"[{session_id}] Client connected to audio WS")
            await ws.send_text(json.dumps({"type": "started"}))
            # Disparar el primer turno del LLM para que el bot hable primero.
            # Esto desbloquea las mute strategies tipo MuteUntilFirstBotComplete.
            await task.queue_frames([LLMRunFrame()])

        @transport.event_handler("on_client_disconnected")
        async def on_client_disconnected(transport, ws):
            logger.info(f"[{session_id}] Client disconnected from audio WS")

        runner = pipecat.pipeline.runner.PipelineRunner()
        await runner.run(task)

    except WebSocketDisconnect:
        logger.info(f"[{session_id}] Audio disconnected")
    except Exception as e:
        logger.error(f"[{session_id}] Audio error: {e}")
        try:
            await ws.send_text(json.dumps({"type": "error", "message": str(e)}))
        except:
            pass
    finally:
        await task_manager.remove(session_id)
        await session_manager.delete_session(session_id)
        try:
            await ws.close()
        except Exception:
            pass
