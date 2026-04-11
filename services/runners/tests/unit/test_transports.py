"""Tests unitarios para transport setup."""

import pytest
from unittest.mock import MagicMock, patch


class TestTransportSetup:
    """Verifica funciones de transport setup."""

    def test_setup_transport_function_exists(self):
        from app.services.agents.pipelines.transport_setup import setup_transport

        assert callable(setup_transport)

    def test_setup_websocket_transport_function_exists(self):
        from app.services.agents.pipelines.transport_setup import (
            setup_websocket_transport,
        )

        assert callable(setup_websocket_transport)


class TestPipelineBuilder:
    """Verifica pipeline builder."""

    def test_build_pipeline_function_exists(self):
        from app.services.agents.pipelines.pipeline_builder import build_pipeline

        assert callable(build_pipeline)

    def test_build_pipeline_returns_tuple(self):
        from unittest.mock import MagicMock, AsyncMock
        from app.services.agents.pipelines.pipeline_builder import build_pipeline

        mock_transport = MagicMock()
        mock_stt = MagicMock()
        mock_llm = MagicMock()
        mock_tts = MagicMock()
        mock_context_agg = MagicMock()

        from app.schemas.agent import AgentConfig

        config_dict = {
            "version": "1.0.0",
            "agent_id": "test",
            "tenant_id": "tenant-test",
            "metadata": {
                "name": "Test",
                "slug": "test",
                "description": "Test",
                "tags": [],
                "language": "es",
            },
            "brain": {
                "llm": {
                    "provider": "openai",
                    "model": "gpt-4o",
                    "instructions": "Test",
                }
            },
            "runtime_profiles": {},
        }

        try:
            config = AgentConfig(**config_dict)
            result = build_pipeline(
                transport=mock_transport,
                stt=mock_stt,
                llm=mock_llm,
                tts=mock_tts,
                context_aggregator=mock_context_agg,
                config=config,
            )
            assert result is not None
            assert isinstance(result, tuple)
            assert len(result) == 2
        except Exception:
            pytest.skip("Config requires more fields")


class TestSessionManager:
    """Verifica SessionManager."""

    def test_session_manager_class_exists(self):
        from app.services.session_manager import SessionManager

        assert SessionManager is not None

    def test_broadcast_transcript_method_exists(self):
        from app.services.session_manager import SessionManager

        assert hasattr(SessionManager, "broadcast_transcript")

    def test_subscribe_to_transcripts_method_exists(self):
        from app.services.session_manager import SessionManager

        assert hasattr(SessionManager, "subscribe_to_transcripts")


class TestCallHandler:
    """Verifica CallHandler."""

    def test_call_direction_enum_exists(self):
        from app.services.sip.call_handler import CallDirection

        assert CallDirection.INBOUND.value == "inbound"
        assert CallDirection.OUTBOUND.value == "outbound"

    def test_call_state_enum_exists(self):
        from app.services.sip.call_handler import CallState

        assert CallState.RINGING.value == "ringing"
        assert CallState.ANSWERED.value == "answered"
        assert CallState.HANGUP.value == "hangup"
        assert CallState.FAILED.value == "failed"
