FROM dunglas/frankenphp:php8.4-bookworm

RUN apt-get update && apt-get install -y git unzip libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && apt-get clean

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --optimize-autoloader --no-interaction

EXPOSE 8080

CMD php -m && php bin/console cache:clear --env=prod --no-debug && php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration && exec frankenphp run --config /app/Caddyfile