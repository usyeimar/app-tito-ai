"""Tests de integración para SIP."""

import pytest
from unittest.mock import MagicMock, patch


class TestSIPIntegration:
    """Tests de integración SIP."""

    @patch("app.api.v1.sip.WebSocketServer")
    def test_sip_router_imports(self, mock_ws):
        """Verifica que el router SIP importa."""
        from app.api.v1.sip import router

        assert router is not None
        assert router.prefix == "/sip"


class TestTrunkService:
    """Tests para trunk service."""

    def test_trunk_service_exists(self):
        """Verifica que trunk_service existe."""
        from app.services.trunk_service import trunk_service

        assert trunk_service is not None


class TestAMIController:
    """Tests para AMI controller."""

    def test_ami_controller_class_exists(self):
        """Verifica que AMIController existe."""
        from app.services.sip.ami_controller import AMIController

        assert AMIController is not None


class TestTransportAliases:
    """Verifica aliases de transport."""

    def test_sip_transport_alias(self):
        """Verifica SIPTransport alias."""
        from app.services.sip.transport import SIPTransport

        assert SIPTransport is not None

    def test_sipwebsocket_transport_classes_exist(self):
        """Verifica clases de WebSocket transport."""
        from app.services.sip.transport import (
            SIPWebSocketTransport,
            SIPWebSocketParams,
        )

        assert SIPWebSocketTransport is not None
        assert SIPWebSocketParams is not None
