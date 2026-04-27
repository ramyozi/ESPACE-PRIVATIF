FROM php:8.2-fpm-alpine

# Outils de base et dependances natives
RUN apk add --no-cache \
        git \
        unzip \
        icu-dev \
        oniguruma-dev \
        libzip-dev \
        autoconf \
        g++ \
        make

# Extensions PHP necessaires
RUN docker-php-ext-install pdo pdo_mysql intl mbstring zip opcache

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Installation des dependances si composer.json est present a la construction
COPY composer.json composer.lock* ./
RUN composer install --no-interaction --no-scripts --prefer-dist --optimize-autoloader || true

EXPOSE 9000
CMD ["php-fpm"]
