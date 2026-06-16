API := apps/api
WEB := apps/web

.DEFAULT_GOAL := help

help: ## List targets
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

install: ## Install api + web dependencies
	composer -d $(API) install
	pnpm -C $(WEB) install

test: ## Run all tests (domain/api + web)
	composer -d $(API) test
	pnpm -C $(WEB) test run

stan: ## Static analysis (PHPStan max)
	composer -d $(API) stan

arch: ## Architecture fitness tests (domain purity)
	composer -d $(API) test -- --group=arch

eval: ## Run the strategy evaluation harness
	php $(API)/artisan league:evaluate

lint: ## Format check (Pint)
	composer -d $(API) lint

up: ## Local dev (docker compose)
	docker compose up --build

.PHONY: help install test stan arch eval lint up
