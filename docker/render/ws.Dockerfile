# Image cloud pour le service WebSocket Ratchet sur Render.
FROM php:8.2-cli-alpine

RUN apk add --no-cache git unzip icu-dev oniguruma-dev libzip-dev postgresql-dev \
 && docker-php-ext-install pdo pdo_mysql pdo_pgsql intl mbstring zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

RUN composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

# Render injecte PORT a l'execution. Le WS doit ecouter dessus pour
# que le routage public fonctionne.
ENV PORT=8081
EXPOSE 8081

# bin/ws-server.php lit WS_HOST/WS_PORT, on les force ici via env Render.
CMD ["sh", "-c", "WS_PORT=${PORT} php bin/ws-server.php"]
