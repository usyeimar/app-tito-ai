"""Tests para endpoints de Metrics."""

import pytest
from unittest.mock import patch, MagicMock


class TestMetricsEndpoints:
    """Tests para endpoints de metrics."""

    def test_metrics_root_endpoint(self):
        from app.api.v1 import metrics_router

        routes = [r.path for r in metrics_router.routes]
        assert "/" in routes


class TestMetricsIntegration:
    """Tests de integración para metrics."""

    def test_metrics_import(self):
        """Verifica que metrics router importa."""
        from app.api.v1 import metrics_router

        assert metrics_router is not None

    def test_metrics_has_routes(self):
        """Verifica que tiene rutas."""
        from app.api.v1 import metrics_router

        assert len(metrics_router.routes) > 0
