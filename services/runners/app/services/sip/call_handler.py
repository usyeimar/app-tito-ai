"""SIP Call Handler - Orchestrates the lifecycle of SIP calls.

When an AudioSocket connection arrives from Asterisk:
1. Extracts call metadata (channel UUID, caller ID, extension)
2. Resolves trunk + agent via TrunkService
3. Fetches AgentConfig from backend/cache
4. Creates SIPAudioSocketTransport
5. Launches Pipecat pipeline (STT → LLM → TTS)
6. Handles cleanup on hangup

This is the SIP equivalent of AgentPipelineEngine + sessions.py._run_session()
"""

import asyncio
import logging
import time
import uuid
from enum import Enum
from typing import Optional, Dict, Any

from pipecat.pipeline.runner import PipelineRunner
from pipecat.pipeline.task import PipelineParams, PipelineTask
from pipecat.frames.frames import EndFrame, LLMRunFrame, TTSSpeakFrame

from app.schemas.agent import AgentConfig
from app.services.sip.audiosocket_server import AudioSocketConnection, AudioSocketServer
from app.services.sip.transport import SIPAudioSocketTransport, SIPAudioSocketParams
from app.services.sip.ami_controller import AMIController
from app.services.trunk_service import trunk_service
from app.services.session_manager import session_manager
from app.services.task_manager import task_manager


class CallDirection(str, Enum):
    """Dirección de la llamada."""

    INBOUND = "inbound"
    OUTBOUND = "outbound"


class CallState(str, Enum):
    """Estado de la llamada."""

    RINGING = "ringing"
    ANSWERED = "answered"
    HANGUP = "hangup"
    FAILED = "failed"


from app.services.webhook_service import WebhookService
from app.services.agents.factory.builder import ServiceFactory
from app.services.agents.pipelines.context_setup import setup_context
from app.services.agents.pipelines.pipeline_builder import build_pipeline
from app.api.v1.metrics import session_duration_seconds, session_errors_total

logger = logging.getLogger(__name__)


class SIPCallHandler:
    """Handles the lifecycle of SIP calls arriving via AudioSocket.

    Ties together AudioSocketServer, AMIController, TrunkService,
    and Pipecat pipelines.
    """

    def __init__(
        self, audiosocket_server: AudioSocketServer, ami: Optional[AMIController] = None
    ):
        self._server = audiosocket_server
        self._ami = ami
        # Track active SIP sessions: channel_uuid → session data
        self._active_sessions: Dict[str, Dict[str, Any]] = {}

        # Set ourselves as the AudioSocket connection handler
        self._server._on_connection = self._on_audiosocket_connection

        # Register AMI event handlers
        if self._ami:
            self._ami.on_dtmf = self._on_ami_dtmf
            self._ami.on_hangup = self._on_ami_hangup

    async def _on_audiosocket_connection(self, conn: AudioSocketConnection):
        """Called when Asterisk opens an AudioSocket connection for a new call.

        This is the main entry point for SIP calls.
        """
        channel_uuid = conn.channel_uuid
        session_id = f"sip_{uuid.uuid4().hex[:12]}"

        logger.info(f"[{session_id}] New SIP call | channel={channel_uuid}")

        try:
            # 1. Get call metadata from AMI
            caller_id = None
            called_extension = None

            if self._ami and self._ami.connected:
                # Try to get channel variables set by dialplan
                caller_id = await self._ami.get_variable(
                    f"AudioSocket/{channel_uuid}", "CALLERID(num)"
                )
                called_extension = await self._ami.get_variable(
                    f"AudioSocket/{channel_uuid}", "AGENT_ID"
                )

            if not called_extension:
                # Fallback: extract from channel UUID or use default
                logger.warning(
                    f"[{session_id}] Could not resolve extension from AMI, "
                    "will need trunk resolution by other means"
                )

            # 2. Resolve trunk and agent
            resolution = await self._resolve_call(channel_uuid, called_extension, conn)
            if not resolution:
                logger.error(f"[{session_id}] Could not resolve trunk/agent for call")
                await conn.send_hangup()
                return

            trunk_data = resolution["trunk_data"]
            agent_id = resolution["agent_id"]
            trunk_id = resolution["trunk_id"]

            logger.info(
                f"[{session_id}] Resolved: trunk={trunk_id} agent={agent_id} caller={caller_id}"
            )

            # 3. Fetch AgentConfig
            # TODO: In production, fetch from backend API or cache
            # For now, we need the config to be passed or cached
            agent_config = await self._get_agent_config(agent_id, trunk_data)
            if not agent_config:
                logger.error(
                    f"[{session_id}] AgentConfig not found for agent_id={agent_id}"
                )
                await conn.send_hangup()
                return

            # 4. Increment active calls on trunk
            await trunk_service.increment_active_calls(trunk_id)

            # 5. Track session
            self._active_sessions[channel_uuid] = {
                "session_id": session_id,
                "trunk_id": trunk_id,
                "agent_id": agent_id,
                "channel_uuid": channel_uuid,
                "caller_id": caller_id,
                "transport": None,
                "task": None,
            }

            # 6. Save session in Redis
            await session_manager.save_session(
                session_id, agent_config, room_name=channel_uuid, provider="sip"
            )

            # 7. Run pipeline
            await self._run_pipeline(session_id, conn, agent_config, trunk_id)

        except asyncio.CancelledError:
            logger.info(f"[{session_id}] SIP session cancelled")
            raise
        except Exception as e:
            logger.exception(f"[{session_id}] SIP call error: {e}")
            session_errors_total.labels(reason="sip_pipeline_error").inc()
        finally:
            await self._cleanup_session(channel_uuid)

    async def _resolve_call(
        self,
        channel_uuid: str,
        called_extension: Optional[str],
        conn: AudioSocketConnection,
    ) -> Optional[Dict[str, Any]]:
        """Resolve which trunk and agent should handle this call."""

        # Try inbound resolution if we have an extension
        if called_extension:
            # Search all workspaces (in production, the workspace would be
            # determined from the SIP domain or trunk credentials)
            # For now, try all trunks
            # TODO: Optimize with a reverse lookup index
            all_trunk_keys = await trunk_service._redis.keys("trunk:index:*")
            for key in all_trunk_keys:
                workspace = key.split(":")[-1]
                result = await trunk_service.resolve_inbound_call(
                    workspace, called_extension
                )
                if result:
                    return result

        # Try register resolution (the trunk_id might be embedded in the channel)
        # Asterisk can set channel variables that map to trunk_id
        # For now, this is a placeholder for when AMI provides trunk context

        return None

    async def _get_agent_config(
        self, agent_id: str, trunk_data: dict
    ) -> Optional[AgentConfig]:
        """Fetch AgentConfig for the agent.

        TODO: In production, this should:
        1. Check Redis cache: "agent_config:{agent_id}"
        2. If not cached, call backend API: GET /api/agents/{agent_id}/config
        3. Cache the result with TTL

        For now, returns None — the caller must provide config or this
        must be implemented with the backend integration.
        """
        # Check if there's a cached config
        raw = await trunk_service._redis.get(f"agent_config:{agent_id}")
        if raw:
            import json

            try:
                return AgentConfig(**json.loads(raw))
            except Exception as e:
                logger.warning(f"Failed to parse cached AgentConfig: {e}")

        return None

    async def _run_pipeline(
        self,
        session_id: str,
        conn: AudioSocketConnection,
        config: AgentConfig,
        trunk_id: str,
    ):
        """Build and run the Pipecat pipeline for a SIP call."""
        start_time = time.monotonic()
        termination_reason = "completed"

        try:
            # 1. Create SIP transport
            from pipecat.audio.vad.silero import SileroVADAnalyzer
            from pipecat.audio.vad.vad_analyzer import VADParams

            vad_config = config.runtime_profiles.vad
            vad_params = VADParams(
                confidence=vad_config.params.confidence
                if vad_config and vad_config.params
                else 0.7,
                start_secs=max(
                    0.4,
                    vad_config.params.start_secs
                    if vad_config and vad_config.params
                    else 0.4,
                ),
                stop_secs=0.2,
                min_volume=vad_config.params.min_volume
                if vad_config and vad_config.params
                else 0.6,
            )

            transport = SIPAudioSocketTransport(
                params=SIPAudioSocketParams(
                    vad_analyzer=SileroVADAnalyzer(params=vad_params)
                ),
                conn=conn,
            )

            # Track transport in session
            if conn.channel_uuid in self._active_sessions:
                self._active_sessions[conn.channel_uuid]["transport"] = transport

            # 2. Create STT, LLM, TTS services
            stt = ServiceFactory.create_stt_service(config)
            llm = ServiceFactory.create_llm_service(config)
            tts = ServiceFactory.create_tts_service(config)

            # 3. Setup context
            context_aggregator = setup_context(
                session_id, config, SileroVADAnalyzer(params=vad_params)
            )

            # 4. Build pipeline (reuse existing builder)
            pipeline, audio_buffer = build_pipeline(
                transport, stt, llm, tts, context_aggregator, config
            )

            # 5. Create pipeline task
            allow_interruptions = (
                config.runtime_profiles.behavior.interruptibility
                if config.runtime_profiles.behavior
                else True
            )
            pipeline_task = PipelineTask(
                pipeline,
                params=PipelineParams(allow_interruptions=allow_interruptions),
            )

            # 6. Register event handlers
            @transport.event_handler("on_first_participant_joined")
            async def on_call_connected(transport, participant):
                if audio_buffer:
                    await audio_buffer.start_recording()
                # Auto speak first if configured
                if (
                    config.runtime_profiles.behavior
                    and config.runtime_profiles.behavior.initial_action == "SPEAK_FIRST"
                ):
                    await pipeline_task.queue_frames([LLMRunFrame()])

                # Emit webhook
                await WebhookService.emit_event(
                    config.tenant_id,
                    config.agent_id,
                    "session.started",
                    f"sip:{conn.channel_uuid}",
                    {
                        "session_id": session_id,
                        "agent_id": config.agent_id,
                        "channel": "sip",
                    },
                    override_url=config.callback_url,
                )

            @transport.event_handler("on_sip_disconnected")
            async def on_call_disconnected(transport, channel_uuid):
                logger.info(f"[{session_id}] SIP disconnected, ending pipeline")
                await pipeline_task.queue_frames([EndFrame()])

            @transport.event_handler("on_dtmf_received")
            async def on_dtmf(transport, digit):
                logger.info(f"[{session_id}] DTMF received: {digit}")
                await session_manager.emit(
                    session_id,
                    {
                        "event": "dtmf.received",
                        "session_id": session_id,
                        "digit": digit,
                    },
                )

            @context_aggregator.user().event_handler("on_user_turn_stopped")
            async def on_user_turn(aggregator, strategy, message):
                await session_manager.emit(
                    session_id,
                    {
                        "event": "transcript.user",
                        "session_id": session_id,
                        "text": message.content,
                        "is_final": True,
                    },
                )

            @context_aggregator.assistant().event_handler("on_assistant_turn_stopped")
            async def on_assistant_turn(aggregator, message):
                await session_manager.emit(
                    session_id,
                    {
                        "event": "transcript.agent",
                        "session_id": session_id,
                        "text": message.content,
                    },
                )

            # 7. Start transport and run pipeline
            await transport.start()

            runner = PipelineRunner()
            await runner.run(pipeline_task)

        except asyncio.CancelledError:
            termination_reason = "cancelled"
            raise
        except Exception as e:
            termination_reason = "error"
            logger.exception(f"[{session_id}] Pipeline error: {e}")
            raise
        finally:
            duration = time.monotonic() - start_time
            session_duration_seconds.observe(duration)

            if termination_reason == "error":
                session_errors_total.labels(reason="sip_pipeline_error").inc()

            # Send session.ended webhook
            try:
                messages = (
                    list(context_aggregator.context.messages)
                    if context_aggregator
                    else []
                )
                await WebhookService.emit_event(
                    config.tenant_id,
                    config.agent_id,
                    "session.ended",
                    f"sip:{conn.channel_uuid}",
                    {
                        "session_id": session_id,
                        "status": termination_reason,
                        "duration": round(duration, 2),
                        "channel": "sip",
                        "transcription": messages,
                    },
                    override_url=config.callback_url,
                )
            except Exception:
                pass

            logger.info(
                f"[{session_id}] SIP session ended | "
                f"duration={duration:.1f}s reason={termination_reason}"
            )

    async def _cleanup_session(self, channel_uuid: str):
        """Clean up after a SIP session ends."""
        session_data = self._active_sessions.pop(channel_uuid, None)
        if not session_data:
            return

        session_id = session_data["session_id"]
        trunk_id = session_data["trunk_id"]

        # Decrement active calls
        await trunk_service.decrement_active_calls(trunk_id)

        # Clean up session from Redis
        await session_manager.delete_session(session_id)

        # Clean up task manager
        await task_manager.remove(session_id)

        logger.info(f"[{session_id}] SIP session cleanup complete")

    # ── AMI Event Handlers ────────────────────────────────────────────────────

    async def _on_ami_dtmf(self, event: Dict[str, Any]):
        """Forward DTMF from AMI to the corresponding transport."""
        uniqueid = event.get("uniqueid", "")
        digit = event.get("digit", "")

        # Find the session by channel UUID (uniqueid maps to channel_uuid)
        for uuid, session in self._active_sessions.items():
            if uuid == uniqueid or uniqueid.startswith(uuid):
                transport = session.get("transport")
                if transport and isinstance(transport, SIPAudioSocketTransport):
                    await transport.inject_dtmf(digit)
                return

    async def _on_ami_hangup(self, event: Dict[str, Any]):
        """Handle AMI hangup event."""
        uniqueid = event.get("uniqueid", "")
        # The AudioSocket connection will close naturally when Asterisk hangs up,
        # which triggers the disconnect event in the transport.
        # This handler is for logging and edge cases.
        logger.debug(f"AMI hangup for uniqueid={uniqueid}")
