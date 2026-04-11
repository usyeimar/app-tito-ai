"""Tests de integración para pipelines."""

import pytest
from unittest.mock import MagicMock, patch


class TestIntegration:
    """Tests de integración básica."""

    def test_app_imports(self):
        """Verifica que la app importa correctamente."""
        from app.main import app

        assert app is not None

    def test_api_router_imports(self):
        """Verifica que el router importa."""
        from app.api.v1.api import api_router

        assert api_router is not None

    def test_health_endpoint(self):
        """Verifica health endpoint."""
        from fastapi.testclient import TestClient
        from app.main import app

        client = TestClient(app)
        response = client.get("/health")

        assert response.status_code == 200
        assert response.json()["status"].lower() == "ok"


class TestPipectTransportIntegration:
    """Verifica integración con Pipecat transports."""

    def test_fastapi_websocket_transport_available(self):
        """Verifica que FastAPIWebsocketTransport está disponible."""
        try:
            from pipecat.transports.fastapi_websocket import FastAPIWebsocketTransport

            assert FastAPIWebsocketTransport is not None
        except ImportError:
            pytest.skip("FastAPIWebsocketTransport not available")

    def test_daily_transport_available(self):
        """Verifica que DailyTransport está disponible."""
        try:
            from pipecat.transports.daily.transport import DailyTransport

            assert DailyTransport is not None
        except ImportError:
            pytest.skip("DailyTransport not available")

    def test_livekit_transport_available(self):
        """Verifica que LiveKitTransport está disponible."""
        try:
            from pipecat.transports.livekit.transport import LiveKitTransport

            assert LiveKitTransport is not None
        except ImportError:
            pytest.skip("LiveKitTransport not available")


class TestVADIntegration:
    """Verifica integración con VAD."""

    def test_silero_vad_available(self):
        """Verifica que SileroVADAnalyzer está disponible."""
        try:
            from pipecat.audio.vad.silero import SileroVADAnalyzer

            assert SileroVADAnalyzer is not None
        except ImportError:
            pytest.skip("SileroVADAnalyzer not available")


class TestTranscriptionEvents:
    """Verifica eventos de transcripción."""

    def test_context_aggregator_pair_available(self):
        """Verifica que LLMContextAggregatorPair está disponible."""
        try:
            from pipecat.processors.aggregators.llm_response_universal import (
                LLMContextAggregatorPair,
            )

            assert LLMContextAggregatorPair is not None
        except ImportError:
            pytest.skip("LLMContextAggregatorPair not available")
