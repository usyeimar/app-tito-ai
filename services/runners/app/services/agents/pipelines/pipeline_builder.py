import logging
import os
import wave
from datetime import datetime
from pipecat.pipeline.pipeline import Pipeline
try:
    from pipecat.processors.audio.audio_player_processor import AudioPlayerProcessor
except ImportError:
    AudioPlayerProcessor = None
from pipecat.processors.audio.audio_buffer_processor import AudioBufferProcessor
from app.schemas.agent import AgentConfig

logger = logging.getLogger(__name__)

def build_pipeline(
    transport, 
    stt, 
    llm, 
    tts, 
    context_aggregator, 
    config: AgentConfig,
    user_idle=None,
    thinking_player=None,
    ambient_player=None,
    rtvi_processor=None
):
    """Construye la lista de procesadores del pipeline."""
    final_processors = [
        transport.input(),
        stt,
        context_aggregator.user(),
        llm,
        tts,
    ]
    
    if rtvi_processor:
        final_processors.append(rtvi_processor)
    
    if ambient_player:
        final_processors.append(ambient_player)
    
    if thinking_player:
        final_processors.append(thinking_player)
    
    if user_idle:
        final_processors.append(user_idle)
        
    final_processors.append(transport.output())
    
    if audio_buffer := None: pass # dummy for logic
    audio_buffer = None
    if config.compliance and config.compliance.record_audio:
        audio_buffer = AudioBufferProcessor()
        final_processors.append(audio_buffer)
        
    final_processors.append(context_aggregator.assistant())
    
    return Pipeline(final_processors), audio_buffer
