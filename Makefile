COMPOSE=docker-compose
PHP=$(COMPOSE) exec php
CONSOLE=$(PHP) bin/console
COMPOSER=$(PHP) composer

full-up: up migrate fixtload

test:
	@${CONSOLE} doctrine:database:create --env=test || true
	@${CONSOLE} doctrine:migrations:migrate --env=test --no-interaction
	@${PHP} bin/phpunit

up:
	@${COMPOSE} up -d

down:
	@${COMPOSE} down

clear:
	@${CONSOLE} cache:clear

migration:
	@${CONSOLE} make:migration

migrate:
	@${CONSOLE} doctrine:migrations:migrate

fixtload:
	@${CONSOLE} doctrine:fixtures:load

require:
	@${COMPOSER} require

encore_dev:
	@${COMPOSE} run node yarn encore dev

encore_prod:
	@${COMPOSE} run node yarn encore production

phpunit:
	@${PHP} bin/phpunit

# В файл local.mk можно добавлять дополнительные make-команды,
# которые требуются лично вам, но не нужны на проекте в целом
-include local.mk