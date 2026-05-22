.DEFAULT_GOAL := help
.PHONY: help build up down logs sh test test-integration quality reindex benchmark

help: ## List available targets
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'

build: ## Build the application image
	docker compose build

up: ## Start the whole stack (app + Postgres + Elasticsearch)
	docker compose up -d --build

down: ## Stop the stack and remove volumes
	docker compose down -v

logs: ## Tail application logs
	docker compose logs -f app

sh: ## Open a shell in the app container
	docker compose exec app sh

test: ## Run the test suite inside the container
	docker compose exec app vendor/bin/phpunit

test-integration: ## Run the integration suite against the live stack
	-docker compose exec database psql -U app -c 'CREATE DATABASE app_test'
	docker compose exec app vendor/bin/phpunit --testsuite integration

quality: ## Run php-cs-fixer (dry-run), PHPStan and the tests
	docker compose exec app composer quality

reindex: ## Rebuild the search read model from Postgres
	docker compose exec app php bin/console search:reindex

benchmark: ## Seed the catalog and measure search latency
	docker compose exec app php bin/console app:benchmark
