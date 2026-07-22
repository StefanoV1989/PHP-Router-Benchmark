.PHONY: install test analyse format format-check verify benchmark benchmark-quick benchmark-full benchmark-memory benchmark-cache benchmark-export docker-build docker-verify docker-benchmark

install:
	composer install --no-interaction --prefer-dist

test:
	composer test

analyse:
	composer analyse

format:
	composer format

format-check:
	composer format:check

verify:
	composer verify

benchmark: benchmark-quick

benchmark-quick:
	composer benchmark:quick

benchmark-full:
	composer benchmark:full

benchmark-memory:
	composer benchmark:memory

benchmark-cache:
	composer benchmark:cache

benchmark-export:
	composer benchmark:export

docker-build:
	docker build -t php-router-benchmarks .

docker-verify:
	docker run --rm php-router-benchmarks composer verify

docker-benchmark:
	docker run --rm -v "$(CURDIR)/results:/app/results" php-router-benchmarks composer benchmark:quick
