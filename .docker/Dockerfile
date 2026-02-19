FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    unzip \
    curl \
    icu-dev \
    oniguruma-dev \
    libzip-dev \
    linux-headers

RUN docker-php-ext-install \
    intl \
    opcache \
    zip \
    mbstring \
    pcntl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app