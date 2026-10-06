#!/bin/sh
# Démarrage du conteneur : clés JWT, cache, migrations, worker du planificateur, puis serveur web.
set -e

cd /app

# Clés JWT générées au démarrage avec la phrase secrète de l'environnement (jamais dans le dépôt).
if [ -z "${JWT_PASSPHRASE}" ]; then
    echo "JWT_PASSPHRASE manquant : définissez-le dans les variables d'environnement." >&2
    exit 1
fi
php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction

php bin/console cache:clear --env=prod --no-debug
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Worker du planificateur : synchronisation des avis toutes les 6 h, rapport du lundi.
# Relancé automatiquement s'il s'arrête (limite de temps ou de mémoire).
(
    while true; do
        php bin/console messenger:consume scheduler_default --time-limit=3600 --memory-limit=192M --no-interaction || true
        sleep 5
    done
) &

exec frankenphp run --config /app/Caddyfile
