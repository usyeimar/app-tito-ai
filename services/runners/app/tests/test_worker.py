import unittest
import sys
from unittest.mock import MagicMock, patch

# Mock the complex dependencies before they are imported by app.worker
sys.modules['app.services.agents.runner'] = MagicMock()
sys.modules['app.services.agents.runner'].spawn_bot = MagicMock()

# Now we can safely import the worker task
from app.worker import spawn_bot_task

class TestWorker(unittest.TestCase):
    
    @patch('app.worker.asyncio.run')
    def test_spawn_bot_task_success(self, mock_asyncio_run):
        # Setup mock agent config dict
        agent_config = {
            "version": "1.0.0",
            "agent_id": "test-agent",
            "tenant_id": "test-tenant",
            "metadata": {
                "name": "Test Agent", "slug": "test-agent", "description": "Test",
                "language": "en", "tags": []
            },
            "brain": {"llm": {"provider": "openai", "model": "gpt-4", "instructions": "You are a test."}},
            "runtime_profiles": {
                "stt": {"provider": "deepgram", "model": "nova-2"},
                "tts": {"provider": "cartesia", "voice_id": "test-voice"}
            }
        }
        
        # Call the task
        result = spawn_bot_task("https://test-room.url", "test-token", agent_config)
        
        # Assertions
        self.assertEqual(result["status"], "success")
        self.assertEqual(result["room_url"], "https://test-room.url")
        self.assertEqual(result["token"], "test-token")
        self.assertIn("session_id", result)
        self.assertEqual(result["ice_config"]["ice_servers"][0]["username"], "g061f2543c0c7aab7d4b087ad407709")
        
        # Verify that spawn_bot was called via asyncio.run
        mock_asyncio_run.assert_called_once()

    @patch('app.worker.asyncio.run')
    def test_spawn_bot_task_error(self, mock_asyncio_run):
        # Force an error
        mock_asyncio_run.side_effect = Exception("Test Error")
        
        # Pass minimal valid config to AgentConfig
        agent_config = {
            "version": "1.0.0", "agent_id": "test", "tenant_id": "test-tenant",
            "metadata": {"name": "Test", "slug": "test", "description": "", "language": "en", "tags": []},
            "brain": {"llm": {"provider": "openai", "model": "gpt-4", "instructions": ""}},
            "runtime_profiles": {"stt": {"provider": "deepgram", "model": "test"}, "tts": {"provider": "cartesia", "voice_id": "test"}}
        }
        
        result = spawn_bot_task("url", "token", agent_config)
        
        self.assertEqual(result["status"], "error")
        self.assertIn("Test Error", result["message"])

if __name__ == '__main__':
    unittest.main()
