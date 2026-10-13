FROM dunglas/frankenphp:php8.4-bookworm

RUN apt-get update && apt-get install -y git unzip libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && apt-get clean

# Réglages PHP de production : erreurs jamais affichées aux visiteurs, version de PHP masquée.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf "expose_php = Off\nopcache.enable = 1\nopcache.memory_consumption = 128\nopcache.max_accelerated_files = 20000\n" > "$PHP_INI_DIR/conf.d/zz-monavispro.ini"

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Prod : sans les outils de dev (tests, fixtures, profiler, maker). Pas de scripts ici :
# le cache est construit au démarrage par docker/start.sh, avec les vraies variables.
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV APP_ENV=prod
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction
RUN mkdir -p config/jwt && rm -f config/jwt/*.pem && chmod +x docker/start.sh

EXPOSE 8080

CMD ["/app/docker/start.sh"]