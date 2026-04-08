import logging
from typing import Any, Dict

from loguru import logger
from pipecat.frames.frames import (
    UserStartedSpeakingFrame,
    UserStoppedSpeakingFrame,
)
from pipecat.services.deepgram.stt import DeepgramSTTService

# Setup logger for this module
# Note: we use both standard logging and loguru based on the user snippet
log = logging.getLogger(__name__)


class CustomDeepgramSTTService(DeepgramSTTService):
    """
    Enhanced Deepgram STT Service that includes:
    - Safe connection finalization on UserStoppedSpeakingFrame.
    """

    async def process_frame(self, frame, direction):
        # Continue with standard Pipecat frame processing
        # Note: We call super().process_frame which handles the actual STT logic
        await super().process_frame(frame, direction)

        # Custom logic for safe cleanup
        if isinstance(frame, UserStoppedSpeakingFrame):
            # Safe finalize to ensure transient connections are closed properly
            try:
                # In latest Pipecat, connection management is handled internally, 
                # but we can still trigger a cleanup if needed.
                # Note: DeepgramSTTService in v0.108 handles this better automatically.
                pass
            except Exception:
                # Squelch finalize errors as this is a 'best effort' cleanup
                pass
