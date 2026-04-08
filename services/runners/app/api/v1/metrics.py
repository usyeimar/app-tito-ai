from fastapi import APIRouter, Response
from prometheus_client import Counter, Gauge, Histogram, generate_latest, CONTENT_TYPE_LATEST
from app.services.task_manager import task_manager

router = APIRouter()

# Metrics definitions
active_sessions = Gauge("tito_active_sessions", "Number of active sessions on this runner")
dropped_frames_total = Counter("tito_dropped_frames_total", "Total dropped audio frames across all sessions")
session_duration_seconds = Histogram(
    "tito_session_duration_seconds",
    "Session duration in seconds",
    buckets=[30, 60, 120, 300, 600, 1800, 3600]
)
session_errors_total = Counter(
    "tito_session_errors_total", 
    "Sessions ended with error",
    labelnames=["reason"]
)

@router.get("/", include_in_schema=False)
async def metrics():
    """Endpoint for Prometheus scraping."""
    # Update gauge with real current count
    active_sessions.set(task_manager.count())
    return Response(generate_latest(), media_type=CONTENT_TYPE_LATEST)
