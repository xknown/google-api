.PHONY: phar autoload

phar: autoload
	php -dphar.readonly=0 box.phar build

autoload: vendor
	composer dump-autoload -a --no-dev

vendor: composer.json composer.lock
	composer install -a --no-dev
