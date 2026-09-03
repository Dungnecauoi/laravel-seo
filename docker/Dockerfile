# Test image: one PHP version, the extensions this package declares, and
# Composer. Built once per PHP version and reused across matrix rows, so a
# full run does not reinstall system packages nine times.
ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli

RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libicu-dev libxml2-dev libzip-dev \
 && docker-php-ext-install -j"$(nproc)" intl \
 && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_MEMORY_LIMIT=-1

WORKDIR /app
