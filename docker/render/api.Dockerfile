# Image cloud pour le service API Slim sur Render.
# On reste en PHP 8.2 alpine, sans nginx : le serveur HTTP integre PHP
# est largement suffisant pour cette V1 et evite un process supplementaire.
FROM php:8.2-cli-alpine

# Outils + dependances natives (pdo_pgsql cote cloud, pdo_mysql conserve
# pour rester compatible avec un eventuel deploiement non-Supabase).
RUN apk add --no-cache git unzip icu-dev oniguruma-dev libzip-dev postgresql-dev \
 && docker-php-ext-install pdo pdo_mysql pdo_pgsql intl mbstring zip opcache

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

# Install des dependances en mode prod
RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

# Render injecte le PORT a l'execution.
ENV PORT=8080
EXPOSE 8080

# Le routeur public/index.php joue le role de front-controller.
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT} -t public public/index.php"]
