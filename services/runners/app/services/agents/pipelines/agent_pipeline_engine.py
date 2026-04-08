import asyncio
import logging
import time
import json
import wave
import os
import base64
import uuid
from datetime import datetime
from typing import List, Optional

from pipecat.pipeline.runner import PipelineRunner
from pipecat.pipeline.task import PipelineParams, PipelineTask
from pipecat.processors.frameworks.rtvi import RTVIProcessor, RTVIConfig, RTVIObserver, RTVIAction, RTVIServiceConfig
from pipecat.frames.frames import EndFrame, LLMRunFrame, TTSSpeakFrame
from pipecat.processors.user_idle_processor import UserIdleProcessor

from app.schemas.agent import AgentConfig
from app.services.agents.factory.builder import ServiceFactory
from app.services.agents.tools.agent_tools import AgentTools
from app.services.webhook_service import WebhookService
from app.services.session_manager import session_manager
from app.services.agents.pipelines.transport_setup import setup_transport
from app.services.agents.pipelines.context_setup import setup_context
from app.services.agents.pipelines.pipeline_builder import build_pipeline
from app.api.v1.metrics import dropped_frames_total, session_duration_seconds, session_errors_total

logger = logging.getLogger(__name__)

MAX_SESSION_DURATION = 3600  # 1 hora máxima

class AgentPipelineEngine:
    """Orquestador del ciclo de vida del agente conversacional (v2)."""

    def __init__(self, room_url: str, token: str, config: AgentConfig, room_name: str):
        self.room_url = room_url
        self.token = token
        self.config = config
        self.room_name = room_name
        self.session_id = str(uuid.uuid4())
        
        # Estado de Runtime
        self.has_started = False
        self._internal_tasks: List[asyncio.Task] = []
        self._transport_ready = asyncio.Event()
        self.start_time: float = 0
        self.dropped_frames: int = 0
        
        # Componentes Pipecat
        self.transport = None
        self.task = None
        self.runner = None
        
        # Lógica de Negocio
        self.tools_handler = AgentTools(agent_id=self.config.agent_id)

    def _create_internal_task(self, coro, name: str) -> asyncio.Task:
        task = asyncio.create_task(coro, name=name)
        self._internal_tasks.append(task)
        return task

    async def _cleanup_internal_tasks(self):
        logger.debug(f"[{self.session_id}] Cleaning up {len(self._internal_tasks)} internal tasks")
        for task in self._internal_tasks:
            if not task.done():
                task.cancel()
        if self._internal_tasks:
            await asyncio.gather(*self._internal_tasks, return_exceptions=True)
        self._internal_tasks = []

    async def _lifecycle_monitor(self, max_duration: float = MAX_SESSION_DURATION):
        """Monitorea desconexiones y tiempos máximos."""
        transport_done = self._create_internal_task(self.transport.wait_disconnected(), name="transport-done")
        # ctx_done = self._create_internal_task(self.context_aggregator.ctx.wait(), name="ctx-done") # Si fuera necesario
        timeout_task = self._create_internal_task(asyncio.sleep(max_duration), name="session-timeout")

        done, pending = await asyncio.wait(
            [transport_done, timeout_task],
            return_when=asyncio.FIRST_COMPLETED
        )

        reason = "unknown"
        if timeout_task in done:
            reason = "max_duration_exceeded"
        elif transport_done in done:
            reason = "transport_disconnected"

        logger.info(f"[{self.session_id}] Lifecycle monitor triggered: {reason}")
        if self.task:
            await self.task.queue_frames([EndFrame()])

    async def run(self):
        """Construye y ejecuta el pipeline con gestión de recursos."""
        self.start_time = time.monotonic()
        termination_reason = "completed"
        
        try:
            # 1. Setup Componentes (Refactorizados)
            self.transport, vad_analyzer = await setup_transport(self.room_url, self.token, self.room_name, self.config)
            
            stt = ServiceFactory.create_stt_service(self.config)
            llm = ServiceFactory.create_llm_service(self.config)
            tts = ServiceFactory.create_tts_service(self.config)
            
            context_aggregator = setup_context(self.session_id, self.config, vad_analyzer)
            
            # 2. Configurar RTVI
            rtvi = self._setup_rtvi(stt, llm, tts)
            self.tools_handler.set_rtvi_handler(rtvi)
            
            # 3. Setup Processors Adicionales (Idle, Audio, Ambient)
            user_idle = await self._setup_idle_processor()
            ambient_player, thinking_player = self._setup_players()
            
            # 4. Construir Pipeline
            pipeline, audio_buffer = build_pipeline(
                self.transport, stt, llm, tts, context_aggregator, self.config,
                user_idle=user_idle, thinking_player=thinking_player, ambient_player=ambient_player,
                rtvi_processor=rtvi
            )
            
            # 5. Registrar Eventos del Transporte y Contexto
            self._register_base_event_handlers(context_aggregator, audio_buffer, thinking_player, ambient_player)

            # 6. Configurar Task
            allow_interruptions = self.config.runtime_profiles.behavior.interruptibility if self.config.runtime_profiles.behavior else True
            self.task = PipelineTask(
                pipeline,
                params=PipelineParams(allow_interruptions=allow_interruptions),
                observers=[RTVIObserver()],
                rtvi_processor=rtvi,
            )
            
            # 7. Registrar Eventos de la Tarea (PipelineTask)
            self._register_task_event_handlers(context_aggregator, audio_buffer)
            
            self.tools_handler.set_queue_handler(self.task.queue_frames)

            # 8. Iniciar Lifecycle Monitor
            self._create_internal_task(self._lifecycle_monitor(), name="lifecycle-monitor")

            # 8.1 Iniciar Control Listener (Redis Pub/Sub)
            self._create_internal_task(self._control_listener(), name="control-listener")

            # 9. Ejecutar
            self.runner = PipelineRunner()
            await self.runner.run(self.task)

        except asyncio.CancelledError:
            termination_reason = "cancelled"
            raise
        except Exception as e:
            termination_reason = "error"
            logger.exception(f"[{self.session_id}] Pipeline error: {e}")
            raise
        finally:
            duration = time.monotonic() - self.start_time
            await self._cleanup_internal_tasks()
            
            # Record Metrics
            session_duration_seconds.observe(duration)
            if termination_reason == "error":
                session_errors_total.labels(reason="pipeline_error").inc()
            elif termination_reason == "cancelled":
                session_errors_total.labels(reason="cancelled").inc()
            
            if self.transport:
                # Pipecat transports often have a cleanup or close method
                try:
                    # check if it's daily or livekit and handle accordingly if needed
                    pass 
                except:
                    pass
            
            logger.info("session_ended", extra={
                "session_id": self.session_id,
                "duration_seconds": round(duration, 2),
                "dropped_frames": self.dropped_frames,
                "termination_reason": termination_reason,
                "agent_id": self.config.agent_id
            })

    async def _control_listener(self):
        """Escucha comandos de control (ej: stop) desde Redis."""
        pubsub = session_manager._redis.pubsub()
        await pubsub.subscribe(f"session:{self.session_id}:control")
        
        try:
            async for message in pubsub.listen():
                if message["type"] == "message":
                    data = json.loads(message["data"])
                    if data.get("action") == "stop":
                        logger.info(f"[{self.session_id}] Remote stop command received")
                        if self.task:
                            await self.task.queue_frames([EndFrame()])
                        break
        except Exception as e:
            logger.error(f"[{self.session_id}] Control listener error: {e}")
        finally:
            await pubsub.unsubscribe(f"session:{self.session_id}:control")
            await pubsub.close()

    def _setup_rtvi(self, stt, llm, tts) -> RTVIProcessor:
        # En RTVI, los nombres de los servicios deben ser 'stt', 'llm' y 'tts'
        # para que coincidan con las peticiones del cliente (client-ready)
        services = {{
            "stt": stt,
            "llm": llm,
            "tts": tts
        }}
        
        rtvi = RTVIProcessor(
            config=RTVIConfig(
                config=[
                    RTVIServiceConfig(service="stt", options=[{{"name": "model", "value": "nova-2"}}]),
                    RTVIServiceConfig(service="tts", options=[{{"name": "voice", "value": "79a125e8-cd45-4c13-8a67-188112f4dd22"}}]),
                    RTVIServiceConfig(service="llm", options=[{{"name": "model", "value": "gpt-4o"}}]),
                ]
            )
        )
        
        # Registrar los servicios explícitamente si el constructor no los tomó
        for name, service in services.items():
            rtvi.register_service(name, service)
            
        async def handle_request_chat_history(rtvi, action):
            return {{"status": "ok", "history": []}}

        rtvi.register_action(RTVIAction(service="app", action="request-chat-history", handler=handle_request_chat_history, result="object"))
        return rtvi

    def _setup_players(self):
        behavior = self.config.runtime_profiles.behavior
        ambient_player = None
        thinking_player = None
        
        try:
            from pipecat.processors.audio.audio_player_processor import AudioPlayerProcessor
        except ImportError:
            AudioPlayerProcessor = None

        if AudioPlayerProcessor and behavior and behavior.ambient_sound and behavior.ambient_sound.enabled:
            ambient_player = AudioPlayerProcessor()

        if AudioPlayerProcessor and behavior and behavior.thinking_sound and behavior.thinking_sound.enabled:
            thinking_player = AudioPlayerProcessor()
            
        return ambient_player, thinking_player

    async def _setup_idle_processor(self) -> Optional[UserIdleProcessor]:
        session_limits = self.config.runtime_profiles.session_limits
        if not session_limits or not session_limits.inactivity_timeout or not session_limits.inactivity_timeout.enabled:
            return None

        inactivity_config = session_limits.inactivity_timeout
        steps = inactivity_config.steps
        if not steps: return None

        self.idle_stage = 0
        async def on_user_idle(frame):
            if self.idle_stage >= len(steps):
                if self.task:
                    await self.task.queue_frames([TTSSpeakFrame(text=inactivity_config.final_message), EndFrame()])
                return
            
            stage = steps[self.idle_stage]
            msg = stage.message[0] if stage.message else "¿Hola?"
            if self.task:
                await self.task.queue_frames([TTSSpeakFrame(text=msg)])
            
            self.idle_stage += 1
            if self.idle_stage < len(steps):
                self.user_idle.timeout = steps[self.idle_stage].wait_seconds

        self.user_idle = UserIdleProcessor(callback=on_user_idle, timeout=steps[0].wait_seconds)
        return self.user_idle

    async def _save_recording(self, audio_buffer) -> Optional[str]:
        """Guarda la grabacion del AudioBufferProcessor en resources/data/recordings/."""
        try:
            audio_buffer.stop_recording()
            audio_data = audio_buffer.get_audio()
            if not audio_data or len(audio_data) == 0:
                logger.warning(f"[{self.session_id}] Recording buffer is empty, skipping save")
                return None

            recordings_dir = os.path.join(
                os.path.dirname(os.path.abspath(__file__)),
                "..", "..", "..", "..", "resources", "data", "recordings"
            )
            recordings_dir = os.path.normpath(recordings_dir)
            os.makedirs(recordings_dir, exist_ok=True)

            timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
            filename = f"session_{self.session_id}_{timestamp}.wav"
            filepath = os.path.join(recordings_dir, filename)

            with wave.open(filepath, "wb") as wf:
                wf.setnchannels(1)
                wf.setsampwidth(2)  # 16-bit
                wf.setframerate(16000)  # 16kHz
                wf.writeframes(audio_data)

            file_size_mb = os.path.getsize(filepath) / (1024 * 1024)
            logger.info(f"[{self.session_id}] Recording saved: {filename} ({file_size_mb:.1f} MB)")
            return filepath
        except Exception as e:
            logger.error(f"[{self.session_id}] Failed to save recording: {e}")
            return None

    def _register_base_event_handlers(self, context_aggregator, audio_buffer, thinking_player, ambient_player):
        """Registra eventos que no dependen de la existencia de self.task."""
        # Transcripciones
        @context_aggregator.user().event_handler("on_user_turn_stopped")
        async def on_user_turn_stopped(aggregator, strategy, message):
            await session_manager.emit(self.session_id, {"event": "transcript.user", "session_id": self.session_id, "text": message.content, "is_final": True})
            if thinking_player and self.config.runtime_profiles.behavior.thinking_sound:
                await thinking_player.play(self.config.runtime_profiles.behavior.thinking_sound.audio, loop=True)

        @context_aggregator.assistant().event_handler("on_assistant_turn_stopped")
        async def on_assistant_turn_stopped(aggregator, message):
            await session_manager.emit(self.session_id, {"event": "transcript.agent", "session_id": self.session_id, "text": message.content})

        # Speaking events
        @self.transport.event_handler("on_bot_started_speaking")
        async def on_bot_started_speaking(transport):
            if thinking_player: await thinking_player.stop()

        # DTMF Support
        @self.transport.event_handler("on_dtmf_received")
        async def on_dtmf_received(transport, digit: str):
            logger.info(f"[{self.session_id}] DTMF received: {digit}")
            await session_manager.emit(self.session_id, {
                "event": "dtmf.received",
                "session_id": self.session_id,
                "digit": digit
            })
            # Inyectar en el contexto para que el LLM lo sepa
            await context_aggregator.context.add_message({
                "role": "system", 
                "content": f"USER_DTMF_INPUT: {digit}. El usuario ha presionado la tecla {digit} en su teclado telefónico. Responde acorde a esto."
            })
            # Forzar una corrida del LLM para responder de inmediato
            if self.task:
                await self.task.queue_frames([LLMRunFrame()])

        # Participant joined
        @self.transport.event_handler("on_first_participant_joined")
        async def on_first_participant_joined(transport, participant):
            if audio_buffer: await audio_buffer.start_recording()
            if self.config.runtime_profiles.behavior and self.config.runtime_profiles.behavior.initial_action == "SPEAK_FIRST":
                if self.task:
                    await self.task.queue_frames([LLMRunFrame()])
            
            if ambient_player and self.config.runtime_profiles.behavior.ambient_sound:
                await ambient_player.play(self.config.runtime_profiles.behavior.ambient_sound.audio, loop=True)

    def _register_task_event_handlers(self, context_aggregator, audio_buffer):
        """Registra eventos que dependen de self.task."""
        @self.task.event_handler("on_pipeline_started")
        async def on_pipeline_started(task, frame):
            self.has_started = True
            # Fire and forget webhook
            self._create_internal_task(
                WebhookService.emit_event(
                    self.config.tenant_id, self.config.agent_id, "session.started", self.room_url,
                    {"session_id": self.session_id, "agent_id": self.config.agent_id},
                    override_url=self.config.callback_url
                ),
                name="webhook-session-started"
            )

        @self.task.event_handler("on_pipeline_finished")
        async def on_pipeline_finished(task, frame):
            # Obtener transcripciones y enviar session.ended
            messages = list(context_aggregator.context.messages)
            duration = time.monotonic() - self.start_time

            # Guardar grabacion si el buffer esta activo
            recording_path = None
            if audio_buffer:
                recording_path = await self._save_recording(audio_buffer)

            async def post_session():
                event_data = {
                    "session_id": self.session_id,
                    "status": "completed",
                    "duration": duration,
                    "transcription": messages,
                }
                if recording_path:
                    event_data["recording_path"] = recording_path
                await WebhookService.emit_event(
                    self.config.tenant_id, self.config.agent_id, "session.ended", self.room_url,
                    event_data,
                    override_url=self.config.callback_url
                )

            self._create_internal_task(post_session(), name="post-session-task")
