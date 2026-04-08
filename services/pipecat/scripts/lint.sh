#!/bin/bash
# Code quality checks with uv

set -e

echo "🔍 Running code quality checks..."

echo "📝 Formatting with black..."
uv run black .

echo "📋 Sorting imports with isort..."
uv run isort .

echo "🔎 Linting with flake8..."
uv run flake8 --exclude .venv .

echo "🏷️  Type checking with mypy..."
uv run mypy --exclude .venv .

echo "✅ All checks passed!"
