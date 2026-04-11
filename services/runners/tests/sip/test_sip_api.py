"""Tests para endpoints SIP."""

import pytest
from unittest.mock import patch, MagicMock


class TestSIPEndpoints:
    """Tests para endpoints SIP."""

    def test_sip_media_websocket_endpoint(self):
        from app.api.v1 import sip_router

        routes = [r.path for r in sip_router.routes]
        assert any("media" in r for r in routes)

    def test_sip_calls_get_endpoint(self):
        from app.api.v1 import sip_router

        routes = [r.path for r in sip_router.routes]
        assert any("calls" in r for r in routes)

    def test_sip_dialplan_endpoint(self):
        from app.api.v1 import sip_router

        routes = [r.path for r in sip_router.routes]
        assert any("dialplan" in r for r in routes)

    def test_sip_health_endpoint(self):
        from app.api.v1 import sip_router

        routes = [r.path for r in sip_router.routes]
        assert any("health" in r for r in routes)


class TestSIPIntegration:
    """Tests de integración SIP."""

    def test_sip_import(self):
        """Verifica que SIP router importa."""
        from app.api.v1 import sip_router

        assert sip_router is not None

    def test_sip_has_routes(self):
        """Verifica que tiene rutas."""
        from app.api.v1 import sip_router

        assert len(sip_router.routes) > 0


class TestSIPTransportFunctions:
    """Tests para funciones de transport SIP."""

    def test_get_sample_rate_function(self):
        """Verifica función de sample rate."""
        from app.api.v1.sip import get_sample_rate

        assert callable(get_sample_rate)

    def test_get_sample_rate_ulaw(self):
        """Verifica sample rate para ulaw."""
        from app.api.v1.sip import get_sample_rate

        assert get_sample_rate("ulaw") == 8000

    def test_get_sample_rate_opus(self):
        """Verifica sample rate para opus."""
        from app.api.v1.sip import get_sample_rate

        assert get_sample_rate("opus") == 48000
