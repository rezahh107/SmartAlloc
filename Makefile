.PHONY: install smoke test phpcs psalm psalm-taint state gen-features gen-context

install:
	composer install --no-interaction --prefer-dist

smoke:
	composer smoke

test:
	composer test

phpcs:
	composer phpcs || true

psalm:
	composer psalm || true

psalm-taint:
	composer psalm:taint || true

gen-features:
	composer gen:features

gen-context:
	composer gen:context

state:
	composer state
