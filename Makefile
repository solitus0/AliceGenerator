ecs:
	vendor/bin/ecs check --config=ecs.php --clear-cache --fix

ecs_check:
	vendor/bin/ecs check --config=ecs.php --clear-cache

rector:
	vendor/bin/rector process

test:
	APP_ENV=test php -d xdebug.mode=off -d memory_limit=-1 vendor/bin/phpunit --configuration ./phpunit.xml.dist --no-coverage

phpstan:
	mkdir -p ./var/phpstan
	php -d memory_limit=1G vendor/bin/phpstan analyse -c phpstan.dist.neon --error-format gitlab > ./var/phpstan/phpstan-report.json

before_push:
	make ecs
	make rector
	make phpstan
	make test
