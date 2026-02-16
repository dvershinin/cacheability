SHELL := /bin/bash

.PHONY: up down build setup test tests logs clean pytest lint phpcs phpcbf phpstan

# ============================================================================
# Linting and Static Analysis
# ============================================================================

lint: phpcs phpstan
	@echo "All linting checks passed!"

phpcs:
	@echo "Running PHP CodeSniffer..."
	@if [ -x "$$HOME/.composer/vendor/bin/phpcs" ]; then \
		$$HOME/.composer/vendor/bin/phpcs; \
	elif command -v phpcs &> /dev/null; then \
		phpcs; \
	else \
		echo "phpcs not installed. Install with:"; \
		echo "  composer global require wp-coding-standards/wpcs dealerdirect/phpcodesniffer-composer-installer"; \
	fi

phpcbf:
	@echo "Auto-fixing PHP CodeSniffer issues..."
	@if [ -x "$$HOME/.composer/vendor/bin/phpcbf" ]; then \
		$$HOME/.composer/vendor/bin/phpcbf || true; \
	elif command -v phpcbf &> /dev/null; then \
		phpcbf || true; \
	else \
		echo "phpcbf not installed. Install with:"; \
		echo "  composer global require wp-coding-standards/wpcs dealerdirect/phpcodesniffer-composer-installer"; \
	fi

phpstan:
	@echo "Running PHPStan..."
	@if command -v phpstan &> /dev/null; then \
		phpstan analyse --no-progress; \
	else \
		echo "phpstan not installed. Install with:"; \
		echo "  composer global require phpstan/phpstan"; \
	fi

# ============================================================================
# Docker / Testing
# ============================================================================

build:
	cd tests && docker compose build --pull --no-cache

up:
	cd tests && docker compose up -d

down:
	cd tests && docker compose down -v

setup:
	bash tests/setup.sh

tests:
	# Ensure stack is up and WP is initialized before tests
	# Use fixed port 8089 to match test expectations
	export TEST_PORT=8089 && \
	$(MAKE) -e up && \
	cd tests && bash setup.sh && \
	docker compose run --rm tester -q

pytest:
	cd tests && docker compose run --rm tester -q

logs:
	cd tests && docker compose logs -f | cat

clean: down
	# Remove any leftover test-related volumes if they exist
	cd tests && volumes=$$(docker volume ls -q | grep -E '(wp_data|db_data)' || true); \
		if [ -n "$$volumes" ]; then docker volume rm $$volumes; fi
