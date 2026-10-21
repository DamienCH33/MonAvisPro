FROM dunglas/frankenphp:php8.4-bookworm

RUN apt-get update && apt-get install -y git unzip libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && apt-get clean

# Réglages PHP de production : erreurs jamais affichées aux visiteurs, version de PHP masquée.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf "expose_php = Off\nopcache.enable = 1\nopcache.memory_consumption = 128\nopcache.max_accelerated_files = 20000\nsession.upload_progress.enabled = 0\n" > "$PHP_INI_DIR/conf.d/zz-monavispro.ini"

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --optimize-autoloader --no-interaction
RUN mkdir -p config/jwt && rm -f config/jwt/*.pem && chmod +x docker/start.sh

EXPOSE 8080

CMD ["/app/docker/start.sh"]