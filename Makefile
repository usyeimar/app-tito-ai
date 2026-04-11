SHELL := /bin/bash

S := ./vendor/bin/sail

.PHONY: up down remove restart rebuild logs cert test test-typesense phpstan-crm lint pail deps setup reset-db ps migrate tenants-migrate tenants-seed reverb-logs reverb-restart postman storage-bootstrap

up:
	$(S) up -d --build
	./scripts/ensure-rustfs-bucket.sh

down:
	$(S) down --remove-orphans

remove:
	$(S) down -v --rmi all --remove-orphans

restart:
	$(S) restart nginx reverb laravel.test pgsql redis typesense rustfs mailpit queue queue-imports queue-outbound-events queue-marketing-events ses-outbound-consumer ses-marketing-consumer scheduler

rebuild:
	$(S) build --pull --no-cache

logs:
	$(S) logs -f nginx reverb laravel.test

reverb-logs:
	$(S) logs -f reverb

reverb-restart:
	$(S) restart reverb

cert:
	./scripts/generate-cert.sh

test:
	$(S) artisan test

test-typesense:
	$(S) exec -e RUN_TYPESENSE_INTEGRATION_TESTS=1 laravel.test php artisan test tests/Typesense

phpstan-crm:
	$(S) exec laravel.test vendor/bin/phpstan analyse --configuration=phpstan.crm.neon

lint:
	$(S) pint --test

pail:
	$(S) artisan pail

migrate:
	$(S) artisan migrate

tenants-migrate:
	$(S) artisan tenants:migrate

tenants-seed:
	$(S) artisan tenants:seed

deps:
	./scripts/composer-install.sh

setup:
	@test -f .env || cp .env.example .env
	@grep -q '^SCOUT_DRIVER=' .env && sed -i 's/^SCOUT_DRIVER=.*/SCOUT_DRIVER=typesense/' .env || echo 'SCOUT_DRIVER=typesense' >> .env
	@grep -q '^TYPESENSE_HOST=' .env || echo 'TYPESENSE_HOST=typesense' >> .env
	@grep -q '^TYPESENSE_PORT=' .env || echo 'TYPESENSE_PORT=8108' >> .env
	@grep -q '^TYPESENSE_PROTOCOL=' .env || echo 'TYPESENSE_PROTOCOL=http' >> .env
	@grep -q '^TYPESENSE_PATH=' .env || echo 'TYPESENSE_PATH=' >> .env
	@grep -q '^TYPESENSE_API_KEY=' .env || echo 'TYPESENSE_API_KEY=xyz' >> .env
	@grep -q '^AWS_ACCESS_KEY_ID=' .env || echo 'AWS_ACCESS_KEY_ID=sail' >> .env
	@grep -q '^AWS_SECRET_ACCESS_KEY=' .env || echo 'AWS_SECRET_ACCESS_KEY=password' >> .env
	./scripts/composer-install.sh
	./scripts/generate-cert.sh
	$(S) build --pull --no-cache
	$(S) up -d --build --remove-orphans
	./scripts/ensure-rustfs-bucket.sh
	$(S) artisan config:clear
	$(S) artisan key:generate
	$(S) artisan migrate
	@echo "Setup complete. Visit https://workupcloud.test"

storage-bootstrap:
	./scripts/ensure-rustfs-bucket.sh

reset-db:
	$(S) down -v --remove-orphans

ps:
	$(S) ps

postman:
	python scripts/merge-postman.py

# ============================================================================
# Runner (Python) Commands
# ============================================================================

.PHONY: runner runner-install runner-dev runner-test runner-test-unit runner-test-integration runner-test-websocket runner-test-sip runner-test-all runner-lint runner-format runner-clean

runner:
	cd services/runners && uv sync

runner-install:
	cd services/runners && uv sync

runner-dev:
	cd services/runners && uv run uvicorn app.main:app --reload --host 0.0.0.0 --port 8000

runner-test: runner-test-all

runner-test-unit:
	cd services/runners && uv run python -m pytest tests/unit -v --tb=short

runner-test-integration:
	cd services/runners && uv run python -m pytest tests/integration -v --tb=short

runner-test-websocket:
	cd services/runners && uv run python -m pytest tests/websocket -v --tb=short

runner-test-sip:
	cd services/runners && uv run python -m pytest tests/sip -v --tb=short

runner-test-all:
	cd services/runners && uv run python -m pytest tests/ -v --tb=short

runner-lint:
	cd services/runners && uv run ruff check app/

runner-format:
	cd services/runners && uv run ruff format app/

runner-clean:
	find services/runners -type d -name __pycache__ -exec rm -rf {} + 2>/dev/null || true
	find services/runners -type f -name "*.pyc" -delete 2>/dev/null || true
	rm -rf services/runners/.pytest_cache 2>/dev/null || true
