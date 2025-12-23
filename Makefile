SHELL := /bin/bash

.PHONY: up down build setup test tests logs clean pytest lint phpcs phpcbf phpstan

# ============================================================================
# Linting and Static Analysis
# ============================================================================

lint: phpcs
	@echo "All linting checks passed!"

phpcs:
	@echo "Running PHP CodeSniffer..."
	@if [ -x "$$HOME/.composer/vendor/bin/phpcs" ]; then \
		$$HOME/.composer/vendor/bin/phpcs --standard=WordPress --extensions=php cacheability.php; \
	elif command -v phpcs &> /dev/null; then \
		phpcs --standard=WordPress --extensions=php cacheability.php; \
	else \
		echo "phpcs not installed. Install with:"; \
		echo "  composer global require wp-coding-standards/wpcs dealerdirect/phpcodesniffer-composer-installer"; \
	fi

phpcbf:
	@echo "Auto-fixing PHP CodeSniffer issues..."
	@if [ -x "$$HOME/.composer/vendor/bin/phpcbf" ]; then \
		$$HOME/.composer/vendor/bin/phpcbf --standard=WordPress --extensions=php cacheability.php || true; \
	elif command -v phpcbf &> /dev/null; then \
		phpcbf --standard=WordPress --extensions=php cacheability.php || true; \
	else \
		echo "phpcbf not installed. Install with:"; \
		echo "  composer global require wp-coding-standards/wpcs dealerdirect/phpcodesniffer-composer-installer"; \
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

