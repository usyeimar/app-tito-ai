"""Tests para endpoints de Sessions."""

import pytest
from unittest.mock import patch, MagicMock


class TestSessionsEndpoints:
    """Tests para endpoints de sessions."""

    def test_create_session_endpoint_exists(self):
        from app.api.v1 import sessions_router

        routes = [r.path for r in sessions_router.routes]
        assert "/" in routes

    def test_get_session_endpoint_exists(self):
        from app.api.v1 import sessions_router

        routes = [r.path for r in sessions_router.routes]
        assert "/{session_id}" in routes

    def test_delete_session_endpoint_exists(self):
        from app.api.v1 import sessions_router

        routes = [r.path for r in sessions_router.routes]
        assert "/{session_id}" in routes

    def test_list_sessions_endpoint_exists(self):
        from app.api.v1 import sessions_router

        routes = [r.path for r in sessions_router.routes]
        assert "/" in routes

    def test_transcript_ws_endpoint_exists(self):
        from app.api.v1 import sessions_router

        routes = [r.path for r in sessions_router.routes]
        assert "/{session_id}/transcript" in routes

    def test_chat_ws_endpoint_exists(self):
        from app.api.v1 import sessions_router

        routes = [r.path for r in sessions_router.routes]
        assert "/{session_id}/chat" in routes

    def test_audio_ws_endpoint_exists(self):
        from app.api.v1 import sessions_router

        routes = [r.path for r in sessions_router.routes]
        assert "/{session_id}/audio" in routes


class TestSessionsIntegration:
    """Tests de integración para sessions."""

    def test_sessions_import(self):
        """Verifica que session router importa."""
        from app.api.v1 import sessions_router

        assert sessions_router is not None

    def test_sessions_has_routes(self):
        """Verifica que tiene rutas."""
        from app.api.v1 import sessions_router

        assert len(sessions_router.routes) > 0
