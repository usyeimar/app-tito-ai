import logging

from pipecat.processors.frame_processor import FrameProcessor

logger = logging.getLogger(__name__)


class RAGProcessor(FrameProcessor):
    """Retrieves context from a vector store before LLM inference."""

    def __init__(self, vector_store_id: str, openai_api_key: str, top_k: int = 5, **kwargs):
        super().__init__(**kwargs)
        self.vector_store_id = vector_store_id
        self.openai_api_key = openai_api_key
        self.top_k = top_k
