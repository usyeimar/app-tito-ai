from fastapi import APIRouter
from app.api.v1.sessions import router as sessions_router
from app.api.v1.metrics import router as metrics_router
from app.api.v1.deployments import router as deployments_router
from app.api.v1.trunks import router as trunks_router
from app.api.v1.sip import router as sip_router

router = APIRouter()

router.include_router(sessions_router, prefix="/sessions", tags=["Sessions"])
router.include_router(metrics_router, prefix="/metrics", tags=["Metrics"])
router.include_router(deployments_router, prefix="/deployments", tags=["Deployments"])
router.include_router(trunks_router, prefix="/trunks", tags=["SIP Trunks"])
router.include_router(sip_router, prefix="/sip", tags=["SIP"])
