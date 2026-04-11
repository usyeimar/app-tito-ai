"""Tests unitarios para AudioSocket protocol."""

import pytest
from app.services.sip.audiosocket_server import (
    AUDIOSOCKET_TYPE_HANGUP,
    AUDIOSOCKET_TYPE_UUID,
    AUDIOSOCKET_TYPE_DTMF,
    AUDIOSOCKET_TYPE_AUDIO,
    AUDIOSOCKET_TYPE_ERROR,
    AUDIO_SAMPLE_RATES,
    AUDIO_SAMPLE_RATE,
    AUDIO_FRAME_SIZE,
)


class TestAudioSocketConstants:
    """Verifica constantes del protocolo AudioSocket."""

    def test_hangup_type(self):
        assert AUDIOSOCKET_TYPE_HANGUP == 0x00

    def test_uuid_type(self):
        assert AUDIOSOCKET_TYPE_UUID == 0x01

    def test_dtmf_type(self):
        assert AUDIOSOCKET_TYPE_DTMF == 0x03

    def test_audio_type(self):
        assert AUDIOSOCKET_TYPE_AUDIO == 0x12

    def test_error_type(self):
        assert AUDIOSOCKET_TYPE_ERROR == 0xFF

    def test_default_sample_rate(self):
        assert AUDIO_SAMPLE_RATE == 16000

    def test_default_frame_size(self):
        assert AUDIO_FRAME_SIZE == 320

    def test_sample_rates_8khz(self):
        assert 0x10 in AUDIO_SAMPLE_RATES
        assert AUDIO_SAMPLE_RATES[0x10] == (8000, 160, "slin8")

    def test_sample_rates_16khz(self):
        assert 0x12 in AUDIO_SAMPLE_RATES
        assert AUDIO_SAMPLE_RATES[0x12] == (16000, 320, "slin16")

    def test_sample_rates_48khz(self):
        assert 0x16 in AUDIO_SAMPLE_RATES
        assert AUDIO_SAMPLE_RATES[0x16] == (48000, 960, "slin48")


class TestAudioSocketConnection:
    """Tests para AudioSocketConnection (sin red)."""

    def test_connection_dataclass(self):
        from dataclasses import is_dataclass
        from app.services.sip.audiosocket_server import AudioSocketConnection

        assert is_dataclass(AudioSocketConnection)
