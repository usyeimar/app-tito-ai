"""Tests para el servicio de sesiones y LiveKit."""

import os
import pytest
from unittest.mock import patch, MagicMock
from fastapi.testclient import TestClient

from app.main import app
from app.services.livekit_service import LiveKitService


client = TestClient(app)


# ---------------------------------------------------------------------------
# AgentConfig payload mínimo válido
# ---------------------------------------------------------------------------
MINIMAL_AGENT_CONFIG = {
    "version": "1.0.0",
    "agent_id": "test-agent-001",
    "metadata": {
        "name": "Test Agent",
        "slug": "test-agent",
        "description": "Agente de prueba",
        "tags": ["test"],
        "language": "es",
    },
    "brain": {
        "llm": {
            "provider": "openai",
            "model": "gpt-4o",
            "instructions": "Eres un asistente de prueba.",
        }
    },
    "runtime_profiles": {
        "stt": {"provider": "deepgram", "model": "nova-2"},
        "tts": {"provider": "cartesia", "voice_id": "test-voice"},
    },
}

# ---------------------------------------------------------------------------
# AgentConfig en ESPAÑOL: OpenAI STT + OpenAI LLM + Cartesia TTS
# ---------------------------------------------------------------------------
SPANISH_AGENT_CONFIG = {
    "version": "1.0.0",
    "agent_id": "tito-es-001",
    "metadata": {
        "name": "Tito Asistente Español",
        "slug": "tito-es",
        "description": "Asistente de voz en español con OpenAI y Cartesia",
        "tags": ["spanish", "voice", "test"],
        "language": "es",
    },
    "brain": {
        "llm": {
            "provider": "openai",
            "model": "gpt-4o",
            "instructions": (
                "Eres Tito, un asistente de voz amigable y profesional. "
                "Respondes SIEMPRE en español de forma concisa y natural. "
                "Ayudas al usuario con sus preguntas de manera clara."
            ),
            "config": {
                "temperature": 0.6,
                "max_tokens": 1024,
            },
        }
    },
    "runtime_profiles": {
        "stt": {
            "provider": "openai",
            "model": "whisper-1",
            "language": "es",
        },
        "tts": {
            "provider": "cartesia",
            "voice_id": os.getenv(
                "CARTESIA_VOICE", "79a125e8-cd45-4c13-8a67-188112f4dd22"
            ),
        },
        "behavior": {
            "interruptibility": True,
            "initial_action": "SPEAK_FIRST",
            "streaming": True,
        },
    },
}


# ===========================================================================
# 1. LiveKitService – generación de tokens (fix VideoGrants)
# ===========================================================================
class TestLiveKitServiceTokens:
    """Verifica que la generación de tokens LiveKit funciona correctamente."""

    def test_create_room_and_tokens_returns_expected_keys(self):
        result = LiveKitService.create_room_and_tokens("sala-test", "usuario-1")

        assert "ws_url" in result
        assert "bot_token" in result
        assert "user_token" in result
        assert "room_name" in result
        assert result["room_name"] == "sala-test"

    def test_tokens_are_valid_jwt_strings(self):
        result = LiveKitService.create_room_and_tokens("sala-test", "usuario-1")

        # Un JWT tiene 3 partes separadas por puntos
        for key in ("bot_token", "user_token"):
            token = result[key]
            assert isinstance(token, str)
            parts = token.split(".")
            assert len(parts) == 3, f"{key} no es un JWT válido"


# ===========================================================================
# 8. Integration: crear sesión con Daily provider
# ===========================================================================
DAILY_SPANISH_CONFIG = {
    "version": "1.0.0",
    "agent_id": "tito-daily-001",
    "metadata": {
        "name": "Tito Daily Español",
        "slug": "tito-daily-es",
        "description": "Asistente de voz en español con Daily",
        "tags": ["spanish", "daily", "test"],
        "language": "es",
    },
    "brain": {
        "llm": {
            "provider": "openai",
            "model": "gpt-4o",
            "instructions": (
                "Eres Tito, un asistente de voz amigable. "
                "Respondes SIEMPRE en español de forma concisa."
            ),
            "config": {"temperature": 0.6},
        }
    },
    "runtime_profiles": {
        "stt": {
            "provider": "openai",
            "model": "whisper-1",
            "language": "es",
        },
        "tts": {
            "provider": "cartesia",
            "voice_id": os.getenv(
                "CARTESIA_VOICE", "79a125e8-cd45-4c13-8a67-188112f4dd22"
            ),
        },
        "transport": {"provider": "daily"},
        "behavior": {
            "interruptibility": True,
            "initial_action": "SPEAK_FIRST",
            "streaming": True,
        },
    },
}


class TestDailySpanishAgentSession:
    """Test de integración con Daily como transport provider."""

    @patch("app.api.v1.sessions.spawn_bot")
    def test_create_daily_session_returns_201(self, mock_spawn):
        """Crea una sesión real con Daily (llama a la API de Daily.co)."""
        mock_spawn.return_value = None

        response = client.post("/api/v1/sessions/", json=DAILY_SPANISH_CONFIG)

        assert response.status_code == 201
        data = response.json()
        assert "session_id" in data
        assert "ws_url" in data
        assert "user_token" in data
        assert "room_name" in data
        assert data["provider"] == "daily"
        # Daily usa la URL de la sala como ws_url
        assert "daily.co" in data["ws_url"]

    @patch("app.api.v1.sessions.spawn_bot")
    def test_daily_spawn_bot_receives_correct_tokens(self, mock_spawn):
        """Verifica que spawn_bot recibe tokens y URL de Daily."""
        mock_spawn.return_value = None

        response = client.post("/api/v1/sessions/", json=DAILY_SPANISH_CONFIG)
        assert response.status_code == 201

        mock_spawn.assert_called_once()
        call_args = mock_spawn.call_args[0]
        room_url = call_args[0]
        bot_token = call_args[1]
        passed_config = call_args[2]

        assert "daily.co" in room_url
        assert len(bot_token) > 20  # token no vacío
        assert passed_config.metadata.language == "es"
        assert passed_config.brain.llm.provider == "openai"
        assert passed_config.runtime_profiles.tts.provider == "cartesia"

    @patch("app.api.v1.sessions.spawn_bot")
    def test_daily_session_creates_different_rooms(self, mock_spawn):
        """Dos sesiones crean salas diferentes."""
        mock_spawn.return_value = None

        resp1 = client.post("/api/v1/sessions/", json=DAILY_SPANISH_CONFIG)
        resp2 = client.post("/api/v1/sessions/", json=DAILY_SPANISH_CONFIG)

        assert resp1.status_code == 201
        assert resp2.status_code == 201
        assert resp1.json()["room_name"] != resp2.json()["room_name"]


class TestAgentConfigSchema:
    """Valida que el schema AgentConfig acepta/rechaza payloads correctamente."""

    def test_minimal_config_is_valid(self):
        from app.schemas.agent import AgentConfig

        config = AgentConfig(**MINIMAL_AGENT_CONFIG)
        assert config.agent_id == "test-agent-001"
        assert config.brain.llm.provider == "openai"

    def test_missing_required_field_raises_error(self):
        from pydantic import ValidationError
        from app.schemas.agent import AgentConfig

        bad = {**MINIMAL_AGENT_CONFIG}
        del bad["brain"]

        with pytest.raises(ValidationError):
            AgentConfig(**bad)


# ===========================================================================
# 3. Endpoint POST /api/v1/sessions/ – crear sesión
# ===========================================================================
class TestCreateSessionEndpoint:
    """Prueba el endpoint de creación de sesiones."""

    @patch("app.api.v1.sessions.spawn_bot")
    def test_create_session_returns_201(self, mock_spawn):
        mock_spawn.return_value = None

        response = client.post("/api/v1/sessions/", json=MINIMAL_AGENT_CONFIG)

        assert response.status_code == 201
        data = response.json()
        assert "session_id" in data
        assert "ws_url" in data
        assert "user_token" in data
        assert "room_name" in data

    @patch("app.api.v1.sessions.spawn_bot")
    def test_create_session_starts_bot_in_background(self, mock_spawn):
        mock_spawn.return_value = None

        client.post("/api/v1/sessions/", json=MINIMAL_AGENT_CONFIG)

        mock_spawn.assert_called_once()

    def test_create_session_with_invalid_payload_returns_422(self):
        response = client.post("/api/v1/sessions/", json={"invalid": True})
        assert response.status_code == 422


# ===========================================================================
# 4. Endpoint GET /api/v1/sessions/ – listar sesiones
# ===========================================================================
class TestListSessionsEndpoint:
    def test_list_sessions_returns_200(self):
        response = client.get("/api/v1/sessions/")
        assert response.status_code == 200
        data = response.json()
        assert "sessions" in data
        assert "supported_providers" in data
        assert "livekit" in data["supported_providers"]


# ===========================================================================
# 5. Endpoint GET /health
# ===========================================================================
class TestHealthEndpoint:
    def test_health_check(self):
        response = client.get("/health")
        assert response.status_code == 200
        assert response.json()["status"] == "ok"


# ===========================================================================
# 6. Agente en Español: OpenAI + Cartesia – creación de servicios
# ===========================================================================
class TestSpanishAgentServices:
    """Verifica que el agente en español (OpenAI STT/LLM + Cartesia TTS) crea servicios correctamente."""

    def test_spanish_config_is_valid(self):
        from app.schemas.agent import AgentConfig

        config = AgentConfig(**SPANISH_AGENT_CONFIG)
        assert config.agent_id == "tito-es-001"
        assert config.metadata.language == "es"
        assert config.brain.llm.provider == "openai"
        assert config.runtime_profiles.stt.provider == "openai"
        assert config.runtime_profiles.tts.provider == "cartesia"

    def test_openai_stt_service_creates(self):
        from app.schemas.agent import AgentConfig
        from app.services.agents.factory.builder import ServiceFactory

        config_dict = {**SPANISH_AGENT_CONFIG}
        config_dict["runtime_profiles"] = {
            **config_dict["runtime_profiles"],
            "stt": {
                **config_dict["runtime_profiles"]["stt"],
                "api_key": os.getenv("OPENAI_API_KEY", "sk-test"),
            },
        }
        config = AgentConfig(**config_dict)
        stt = ServiceFactory.create_stt_service(config)
        assert stt is not None
        assert "OpenAI" in type(stt).__name__

    def test_openai_llm_service_creates(self):
        from app.schemas.agent import AgentConfig
        from app.services.agents.factory.builder import ServiceFactory

        config_dict = {**SPANISH_AGENT_CONFIG}
        config_dict["brain"] = {
            "llm": {
                **config_dict["brain"]["llm"],
                "api_key": os.getenv("OPENAI_API_KEY", "sk-test"),
            },
        }
        config = AgentConfig(**config_dict)
        llm = ServiceFactory.create_llm_service(config)
        assert llm is not None
        assert "OpenAI" in type(llm).__name__

    def test_cartesia_tts_service_creates(self):
        from app.schemas.agent import AgentConfig
        from app.services.agents.factory.builder import ServiceFactory

        config_dict = {**SPANISH_AGENT_CONFIG}
        config_dict["runtime_profiles"] = {
            **config_dict["runtime_profiles"],
            "tts": {
                **config_dict["runtime_profiles"]["tts"],
                "api_key": os.getenv("CARTESIA_API_KEY", "sk-test"),
            },
        }
        config = AgentConfig(**config_dict)
        tts = ServiceFactory.create_tts_service(config)
        assert tts is not None
        assert "Cartesia" in type(tts).__name__


# ===========================================================================
# 7. Integration: crear sesión completa con agente en español
# ===========================================================================
class TestSpanishAgentSession:
    """Test de integración: crea una sesión completa con el agente en español."""

    @patch("app.api.v1.sessions.spawn_bot")
    def test_create_spanish_session_returns_201(self, mock_spawn):
        mock_spawn.return_value = None

        response = client.post("/api/v1/sessions/", json=SPANISH_AGENT_CONFIG)

        assert response.status_code == 201
        data = response.json()
        assert "session_id" in data
        assert "ws_url" in data
        assert "user_token" in data
        assert "room_name" in data
        assert data["provider"] == "livekit"

    @patch("app.api.v1.sessions.spawn_bot")
    def test_spawn_bot_receives_spanish_config(self, mock_spawn):
        mock_spawn.return_value = None

        response = client.post("/api/v1/sessions/", json=SPANISH_AGENT_CONFIG)
        assert response.status_code == 201

        mock_spawn.assert_called_once()
        call_args = mock_spawn.call_args
        # El tercer argumento es el AgentConfig
        passed_config = call_args[0][2]
        assert passed_config.metadata.language == "es"
        assert passed_config.brain.llm.instructions.startswith("Eres Tito")
