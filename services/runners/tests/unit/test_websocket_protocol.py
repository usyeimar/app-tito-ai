"""Tests unitarios para WebSocket protocol (Asterisk chan_websocket)."""

import pytest
from app.services.sip.websocket_server import (
    WSCommand,
    WSEvent,
    ControlFormat,
    MediaDirection,
)


class TestWebSocketCommands:
    """Verifica comandos del protocolo WebSocket."""

    def test_answer_command(self):
        assert WSCommand.ANSWER == "ANSWER"

    def test_hangup_command(self):
        assert WSCommand.HANGUP == "HANGUP"

    def test_start_media_buffering(self):
        assert WSCommand.START_MEDIA_BUFFERING == "START_MEDIA_BUFFERING"

    def test_stop_media_buffering(self):
        assert WSCommand.STOP_MEDIA_BUFFERING == "STOP_MEDIA_BUFFERING"

    def test_flush_media(self):
        assert WSCommand.FLUSH_MEDIA == "FLUSH_MEDIA"

    def test_pause_media(self):
        assert WSCommand.PAUSE_MEDIA == "PAUSE_MEDIA"

    def test_continue_media(self):
        assert WSCommand.CONTINUE_MEDIA == "CONTINUE_MEDIA"

    def test_mark_media(self):
        assert WSCommand.MARK_MEDIA == "MARK_MEDIA"

    def test_get_status(self):
        assert WSCommand.GET_STATUS == "GET_STATUS"

    def test_report_queue_drained(self):
        assert WSCommand.REPORT_QUEUE_DRAINED == "REPORT_QUEUE_DRAINED"

    def test_set_media_direction(self):
        assert WSCommand.SET_MEDIA_DIRECTION == "SET_MEDIA_DIRECTION"


class TestWebSocketEvents:
    """Verifica eventos del protocolo WebSocket."""

    def test_media_start_event(self):
        assert WSEvent.MEDIA_START == "MEDIA_START"

    def test_dtmf_end_event(self):
        assert WSEvent.DTMF_END == "DTMF_END"

    def test_media_xoff_event(self):
        assert WSEvent.MEDIA_XOFF == "MEDIA_XOFF"

    def test_media_xon_event(self):
        assert WSEvent.MEDIA_XON == "MEDIA_XON"

    def test_status_event(self):
        assert WSEvent.STATUS == "STATUS"

    def test_media_buffering_completed_event(self):
        assert WSEvent.MEDIA_BUFFERING_COMPLETED == "MEDIA_BUFFERING_COMPLETED"

    def test_media_mark_processed_event(self):
        assert WSEvent.MEDIA_MARK_PROCESSED == "MEDIA_MARK_PROCESSED"

    def test_queue_drained_event(self):
        assert WSEvent.QUEUE_DRAINED == "QUEUE_DRAINED"


class TestWebSocketEnums:
    """Verifica enums del protocolo."""

    def test_control_format_json(self):
        assert ControlFormat.JSON.value == "json"

    def test_control_format_plain_text(self):
        assert ControlFormat.PLAIN_TEXT.value == "plain-text"

    def test_media_direction_in(self):
        assert MediaDirection.IN.value == "in"

    def test_media_direction_out(self):
        assert MediaDirection.OUT.value == "out"

    def test_media_direction_both(self):
        assert MediaDirection.BOTH.value == "both"


class TestWebSocketServerClass:
    """Tests para WebSocketServer (sin red)."""

    def test_websocket_server_class_exists(self):
        from app.services.sip.websocket_server import WebSocketServer

        assert WebSocketServer is not None

    def test_websocket_connection_class_exists(self):
        from app.services.sip.websocket_server import WebSocketConnection

        assert WebSocketConnection is not None
