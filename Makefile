# sdi-ops-monitor — Makefile
# Stack: PHP 8.2 / CakePHP 5 / MySQL / AWS
# Usage: make <target>

PHP      := php
COMPOSER := composer
CAKE     := $(PHP) bin/cake
PHPUNIT  := ./vendor/bin/phpunit

.PHONY: help install up migrate seed test cs-check cs-fix routes shell clean

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

# ── Setup ────────────────────────────────────────────────────

install: ## Install PHP dependencies
	$(COMPOSER) install --no-interaction

up: ## Start built-in PHP dev server on :8080
	$(PHP) -S 0.0.0.0:8080 -t webroot/

# ── Database ─────────────────────────────────────────────────

migrate: ## Run pending migrations
	$(CAKE) migrations migrate

migrate-rollback: ## Rollback last migration
	$(CAKE) migrations rollback

seed: ## Run database seeds (fixtures)
	$(CAKE) migrations seed

# ── Tests ────────────────────────────────────────────────────

test: ## Run PHPUnit test suite
	$(PHPUNIT) --colors=always

test-coverage: ## Run tests with HTML coverage report
	$(PHPUNIT) --coverage-html tmp/coverage

# ── Code Quality ─────────────────────────────────────────────

cs-check: ## Check code style (CakePHP standard)
	$(COMPOSER) cs-check

cs-fix: ## Auto-fix code style
	$(COMPOSER) cs-fix

# ── CakePHP CLI ──────────────────────────────────────────────

routes: ## List all registered routes
	$(CAKE) routes

shell: ## Open CakePHP interactive shell
	$(CAKE) console

# ── Cleanup ──────────────────────────────────────────────────

clean: ## Clear CakePHP caches
	$(CAKE) cache clear_all
