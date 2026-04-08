import pytest
from unittest.mock import MagicMock, patch
from app.services.agents.factory.builder import ServiceFactory, AudioConfig
from app.schemas.agent import AgentConfig

@pytest.fixture
def mock_agent_config():
    """Provides a basic mock AgentConfig for testing."""
    config = MagicMock(spec=AgentConfig)
    
    # Mock runtime_profiles
    config.runtime_profiles = MagicMock()
    config.runtime_profiles.stt = MagicMock()
    config.runtime_profiles.stt.provider = "deepgram"
    config.runtime_profiles.stt.model = "nova-2"
    config.runtime_profiles.stt.api_key = "test_stt_key"
    config.runtime_profiles.stt.language = "en"
    
    config.runtime_profiles.tts = MagicMock()
    config.runtime_profiles.tts.provider = "cartesia"
    config.runtime_profiles.tts.voice_id = "test_voice"
    config.runtime_profiles.tts.api_key = "test_tts_key"
    
    # Mock brain
    config.brain = MagicMock()
    config.brain.llm = MagicMock()
    config.brain.llm.provider = "openai"
    config.brain.llm.model = "gpt-4o"
    config.brain.llm.api_key = "test_llm_key"
    config.brain.llm.instructions = "You are a test assistant"
    config.brain.llm.config = MagicMock()
    config.brain.llm.config.temperature = 0.7
    
    # Mock metadata
    config.metadata = MagicMock()
    config.metadata.language = "en"
    
    return config

@patch("app.services.agents.factory.builder.CustomDeepgramSTTService")
def test_create_stt_service_deepgram(mock_stt, mock_agent_config):
    mock_agent_config.runtime_profiles.stt.provider = "deepgram"
    ServiceFactory.create_stt_service(mock_agent_config)
    mock_stt.assert_called_once()

@patch("app.services.agents.factory.builder.CartesiaTTSService")
def test_create_tts_service_cartesia(mock_tts, mock_agent_config):
    mock_agent_config.runtime_profiles.tts.provider = "cartesia"
    ServiceFactory.create_tts_service(mock_agent_config)
    mock_tts.assert_called_once()

@patch("app.services.agents.factory.builder.OpenAILLMService")
def test_create_llm_service_openai(mock_llm, mock_agent_config):
    mock_agent_config.brain.llm.provider = "openai"
    ServiceFactory.create_llm_service(mock_agent_config)
    mock_llm.assert_called_once()

@patch("app.services.agents.factory.builder.GoogleLLMService")
def test_create_llm_service_google(mock_llm, mock_agent_config):
    mock_agent_config.brain.llm.provider = "google"
    ServiceFactory.create_llm_service(mock_agent_config)
    mock_llm.assert_called_once()
