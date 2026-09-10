CLI_ARGS := $(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))
$(eval $(sort $(subst :,\:,$(CLI_ARGS))):;@:)

PRIMARY_GOAL := $(firstword $(MAKECMDGOALS))
ifeq ($(PRIMARY_GOAL),)
    PRIMARY_GOAL := help
endif

-include .env

STACK_NAME ?= bot
IMAGE ?= app-backend
IMAGE_TAG ?= latest
SERVICE ?= backend

export COMPOSE_PROJECT_NAME=${STACK_NAME}
DOCKER_COMPOSE_DEV := docker compose -f ../docker-compose.yml
DOCKER_COMPOSE_TEST := $(DOCKER_COMPOSE_DEV)

# One-off containers inherit the service labels, so traefik would add them as a second
# server for admin-api.app.test and half the requests would 502. Opt them out.
DOCKER_RUN := $(DOCKER_COMPOSE_DEV) run --rm --label traefik.enable=false
DOCKER_RUN_TEST := $(DOCKER_COMPOSE_TEST) run --rm --label traefik.enable=false

#
# Development
#

ifeq ($(PRIMARY_GOAL),build)
build: ## Build docker images.
	$(DOCKER_COMPOSE_DEV) build $(CLI_ARGS)
endif

ifeq ($(PRIMARY_GOAL),up)
up: ## Up the dev environment.
	$(DOCKER_COMPOSE_DEV) up -d --remove-orphans
endif

ifeq ($(PRIMARY_GOAL),down)
down: ## Down the dev environment.
	$(DOCKER_COMPOSE_DEV) down --remove-orphans
endif

ifeq ($(PRIMARY_GOAL),stop)
stop: ## Stop the dev environment.
	$(DOCKER_COMPOSE_DEV) stop
endif

ifeq ($(PRIMARY_GOAL),clear)
clear: ## Remove development docker containers and volumes.
	$(DOCKER_COMPOSE_DEV) down --volumes --remove-orphans
endif

ifeq ($(PRIMARY_GOAL),shell)
shell: ## Get into container shell.
	$(DOCKER_COMPOSE_DEV) exec $(SERVICE) /bin/bash
endif

#
# Tools
#

ifeq ($(PRIMARY_GOAL),yii)
yii: ## Execute Yii command.
	$(DOCKER_RUN) $(SERVICE) ./yii $(CLI_ARGS)
.PHONY: yii
endif

ifeq ($(PRIMARY_GOAL),composer)
composer: ## Run Composer.
	$(DOCKER_RUN) $(SERVICE) composer $(CLI_ARGS)
endif

ifeq ($(PRIMARY_GOAL),rector)
rector: ## Run Rector.
	$(DOCKER_RUN) $(SERVICE) ./vendor/bin/rector $(CLI_ARGS)
endif

ifeq ($(PRIMARY_GOAL),cs-fix)
cs-fix: ## Run PHP CS Fixer.
	$(DOCKER_RUN) $(SERVICE) ./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php --diff
endif

#
# Tests and analysis
#

ifeq ($(PRIMARY_GOAL),test)
test: ## Run tests.
	$(DOCKER_RUN_TEST) $(SERVICE) ./vendor/bin/codecept run $(CLI_ARGS)
endif

ifeq ($(PRIMARY_GOAL),test-coverage)
test-coverage: ## Run tests with coverage.
	$(DOCKER_RUN_TEST) $(SERVICE) ./vendor/bin/codecept run --coverage --coverage-html --disable-coverage-php
endif

ifeq ($(PRIMARY_GOAL),codecept)
codecept: ## Run Codeception.
	$(DOCKER_RUN_TEST) $(SERVICE) ./vendor/bin/codecept $(CLI_ARGS)
endif

ifeq ($(PRIMARY_GOAL),psalm)
psalm: ## Run Psalm.
	$(DOCKER_RUN) $(SERVICE) ./vendor/bin/psalm $(CLI_ARGS)
endif

ifeq ($(PRIMARY_GOAL),phpstan)
phpstan: ## Run PHPStan.
	$(DOCKER_RUN) $(SERVICE) ./vendor/bin/phpstan analyse --memory-limit=512M $(CLI_ARGS)
endif

ifeq ($(PRIMARY_GOAL),composer-dependency-analyser)
composer-dependency-analyser: ## Run Composer Dependency Analyser.
	$(DOCKER_RUN) $(SERVICE) ./vendor/bin/composer-dependency-analyser --config=composer-dependency-analyser.php $(CLI_ARGS)
endif

#
# Production
#

ifeq ($(PRIMARY_GOAL),prod-build)
prod-build: ## Build an image.
	docker build --file Dockerfile --target prod --pull -t ${IMAGE}:${IMAGE_TAG} .
endif

ifeq ($(PRIMARY_GOAL),prod-push)
prod-push: ## Push image to repository.
	docker push ${IMAGE}:${IMAGE_TAG}
endif

ifeq ($(PRIMARY_GOAL),prod-deploy)
prod-deploy: ## Deploy to production.
	@echo "Production compose is not configured for this project."
endif

#
# Other
#

ifeq ($(PRIMARY_GOAL),help)
help: ## This help.
	@awk 'BEGIN { printf "\nUsage:\n  make \033[36m<target>\033[0m\n" } \
	/^#$$/ { blank = 1; next } \
	blank && /^# [a-zA-Z]/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 3); blank = 0; next } \
	/^[a-zA-Z_-]+:([^=]|$$)/ { \
		split($$0, parts, "##"); \
		target = parts[1]; sub(/:.*/, "", target); \
		desc = parts[2]; \
		gsub(/^[[:space:]]+|[[:space:]]+$$/, "", desc); \
		printf "  \033[36m%-25s\033[0m %s\n", target, desc; \
		blank = 0; \
	}' $(MAKEFILE_LIST)
endif
