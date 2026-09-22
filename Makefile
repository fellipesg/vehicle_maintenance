# Docker is the local runtime. Override the compose binary with e.g.
#   make up DC=docker-compose
DC       ?= docker compose
APP_PORT ?= 8080
EXEC     := $(DC) exec app
NODE     := $(DC) run --rm node

.DEFAULT_GOAL := help
.PHONY: help up down restart install node-install dev build test test-pgsql migrate shell logs ps

help: ## List targets
	@grep -E '^[a-zA-Z_-]+:.*## ' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*## "} {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

up: ## Build and start app, nginx, db (Postgres), queue, scheduler
	$(DC) up -d --build
	@echo "App: http://localhost:$(APP_PORT)"

down: ## Stop the stack (keeps the database volume)
	$(DC) --profile frontend down

restart: down up ## Restart the stack

install: up node-install ## First run: .env, composer/npm deps, APP_KEY, migrations
	@test -f .env || cp .env.example .env
	$(EXEC) composer install --no-interaction
	@grep -qE '^APP_KEY=.+' .env || $(EXEC) php artisan key:generate
	$(EXEC) php artisan migrate --force
	$(NODE) npm run build

node-install: ## npm ci into the node_modules volume (Linux binaries)
	$(DC) run --rm --no-deps -u root node chown node:node node_modules
	$(NODE) npm ci --no-audit --no-fund

dev: ## Vite dev server with HMR on :5173 (Ctrl-C to stop)
	@test -n "$$($(DC) run --rm --no-deps node ls node_modules 2>/dev/null)" || $(MAKE) node-install
	$(DC) run --rm --service-ports node

build: ## Production asset build into public/build
	$(NODE) npm run build

test: ## Test suite in the container (sqlite in-memory, phpunit.xml)
	$(EXEC) php artisan test --compact

test-pgsql: ## Test suite against the compose Postgres (phpunit.pgsql.xml)
	$(EXEC) php artisan test --compact --configuration=phpunit.pgsql.xml

migrate: ## Run migrations on the compose Postgres
	$(EXEC) php artisan migrate

shell: ## Shell in the app container
	$(EXEC) bash

logs: ## Follow logs (app, queue, scheduler, nginx)
	$(DC) logs -f --tail=100 app queue scheduler nginx

ps: ## Container status
	$(DC) ps
