# syntax=docker/dockerfile:1

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts --ignore-platform-reqs

FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor /app/vendor
RUN npm run build

FROM php:8.3-fpm-bookworm AS app

ARG UID=1000
ARG GID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
    bash \
    curl \
    git \
    libfreetype6-dev \
    libicu-dev \
    libjpeg62-turbo-dev \
    libonig-dev \
    libpng-dev \
    libxml2-dev \
    libzip-dev \
    unzip \
    zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) bcmath gd intl mbstring pdo_mysql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/dsms.ini

COPY --chown=${UID}:${GID} . .
COPY --from=vendor --chown=${UID}:${GID} /app/vendor ./vendor
COPY --from=frontend --chown=${UID}:${GID} /app/public/build ./public/build

RUN mkdir -p storage bootstrap/cache \
    && chown -R ${UID}:${GID} storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

USER ${UID}:${GID}

EXPOSE 9000
CMD ["php-fpm"]
