"""Tests for the Redis command consumer."""

import json
import pytest
import pytest_asyncio
from unittest.mock import AsyncMock, patch

from app.services.command_consumer import CommandConsumer, COMMANDS_KEY


@pytest_asyncio.fixture
async def consumer():
    c = CommandConsumer()
    c._redis = AsyncMock()
    yield c
    c._running = False


@pytest.mark.asyncio
async def test_handle_terminate(consumer):
    """session.terminate should stop the task and clean up."""
    with patch("app.services.command_consumer.session_manager") as mock_sm, \
         patch("app.services.command_consumer.task_manager") as mock_tm:
        mock_sm.get_session = AsyncMock(return_value={
            "room_name": "room_test",
            "provider": "livekit",
        })
        mock_tm.stop = AsyncMock()

        message = json.dumps({
            "request_id": "req_001",
            "command": "session.terminate",
            "payload": {"session_id": "sess_kill"},
        })

        await consumer._handle_message(message)

        mock_sm.get_session.assert_called_once_with("sess_kill")
        mock_tm.stop.assert_called_once_with("sess_kill")


@pytest.mark.asyncio
async def test_terminate_missing_session(consumer):
    """Terminate for unknown session should log warning and skip."""
    with patch("app.services.command_consumer.session_manager") as mock_sm, \
         patch("app.services.command_consumer.task_manager") as mock_tm:
        mock_sm.get_session = AsyncMock(return_value=None)
        mock_tm.stop = AsyncMock()

        message = json.dumps({
            "command": "session.terminate",
            "payload": {"session_id": "sess_ghost"},
        })

        await consumer._handle_message(message)

        mock_tm.stop.assert_not_called()


@pytest.mark.asyncio
async def test_ignores_invalid_json(consumer):
    """Invalid JSON should be skipped."""
    await consumer._handle_message("not json {{{")


@pytest.mark.asyncio
async def test_unknown_command(consumer):
    """Unknown commands should be logged and skipped."""
    message = json.dumps({
        "command": "unknown.thing",
        "payload": {},
    })
    await consumer._handle_message(message)


@pytest.mark.asyncio
async def test_listen_keys_includes_host(consumer):
    """Listen keys should include host-specific key first."""
    with patch("app.services.command_consumer.settings") as mock_settings:
        mock_settings.HOST_ID = "runner-abc"
        keys = consumer._listen_keys()
        assert keys[0] == f"{COMMANDS_KEY}:runner-abc"
        assert keys[1] == COMMANDS_KEY
