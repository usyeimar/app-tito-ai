"""Tests para endpoints WebSocket - estructura de rutas."""

import pytest
from unittest.mock import MagicMock, patch


class TestWebSocketEndpoints:
    """Verifica existencia de endpoints WebSocket en Sessions."""

    def test_transcript_endpoint_exists(self):
        from app.api.v1 import sessions_router

        routes = [r.path for r in sessions_router.routes]
        assert "/{session_id}/transcript" in routes

    def test_chat_endpoint_exists(self):
        from app.api.v1 import sessions_router

        routes = [r.path for r in sessions_router.routes]
        assert "/{session_id}/chat" in routes

    def test_audio_endpoint_exists(self):
        from app.api.v1 import sessions_router

        routes = [r.path for r in sessions_router.routes]
        assert "/{session_id}/audio" in routes


class TestSessionLinks:
    """Verifica generación de links HATEOAS."""

    def test_get_session_links_function_exists(self):
        from app.api.v1.sessions import get_session_links

        assert callable(get_session_links)
