#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

if ! command -v git >/dev/null 2>&1; then
  printf "[composer-install] git is required because dependencies are fetched from Git repositories (stancl/tenancy).\n" >&2
  exit 1
fi

# Prefer local Composer if PHP >= 8.2 is available
if command -v composer >/dev/null 2>&1 && command -v php >/dev/null 2>&1; then
  if php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' >/dev/null 2>&1; then
    printf "[composer-install] Using local Composer (PHP %s)\n" "$(php -r 'echo PHP_VERSION;')"
    COMPOSER_MEMORY_LIMIT="-1" composer install
    exit 0
  fi
  printf "[composer-install] Local PHP is older than 8.2; falling back to Dockerised Composer.\n" >&2
fi

if ! command -v docker >/dev/null 2>&1; then
  printf "[composer-install] Docker is required for the Composer fallback.\n" >&2
  exit 1
fi

docker info >/dev/null 2>&1 || {
  printf "[composer-install] Docker daemon is not running. Start Docker Desktop or the Docker service first.\n" >&2
  exit 1
}

# Ensure Composer cache directory exists for mount
COMPOSER_CACHE_DIR="${COMPOSER_HOME:-$HOME/.composer}/cache"
mkdir -p "$COMPOSER_CACHE_DIR"

printf "[composer-install] Running composer install via docker image.\n"
exec docker run --rm \
  --user "$(id -u):$(id -g)" \
  --volume "$ROOT_DIR":/app \
  --volume "$COMPOSER_CACHE_DIR":/tmp/composer-cache \
  --env COMPOSER_CACHE_DIR=/tmp/composer-cache \
  --env COMPOSER_MEMORY_LIMIT=-1 \
  --workdir /app \
  composer install --ignore-platform-reqs