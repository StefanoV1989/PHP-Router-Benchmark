FROM composer:2.8 AS composer-bin

FROM php:8.4.1-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git libzip-dev unzip \
    && docker-php-ext-install opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
COPY docker/php-benchmark.ini /usr/local/etc/php/conf.d/zz-benchmark.ini

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress
COPY . .
RUN composer dump-autoload --optimize --no-interaction

CMD ["composer", "verify"]
