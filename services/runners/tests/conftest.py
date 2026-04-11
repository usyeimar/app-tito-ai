"""Pytest configuration."""

import warnings

from dotenv import load_dotenv

# Load environment variables
load_dotenv()

# Suppress all deprecation warnings during tests
warnings.filterwarnings("ignore", category=DeprecationWarning)
warnings.filterwarnings("ignore", category=FutureWarning)
warnings.filterwarnings("ignore", message=".*audioop.*")
warnings.filterwarnings("ignore", message=".*websockets.*")
warnings.filterwarnings("ignore", message=".*WebSocketServerProtocol.*")
