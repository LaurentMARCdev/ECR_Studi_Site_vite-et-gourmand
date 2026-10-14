#!/bin/sh
# ═══════════════════════════════════════════════════════════════
# Point d'entrée du conteneur "app"
# ═══════════════════════════════════════════════════════════════
# 1. Installe les dépendances Composer si le dossier vendor/ manque
#    (cas du code monté depuis l'hôte)
# 2. Attend que PostgreSQL accepte les connexions
# 3. Joue les migrations Doctrine
# 4. (optionnel) Charge le jeu de données de démonstration
# 5. (optionnel) Synchronise MongoDB depuis PostgreSQL (app:sync-mongo)
# 6. Lance Apache
# ═══════════════════════════════════════════════════════════════
set -e

cd /var/www/api

if [ ! -f vendor/autoload_runtime.php ]; then
    echo "→ Installation des dépendances Composer…"
    composer install --no-interaction --prefer-dist --no-progress --no-scripts
fi

mkdir -p var
chown -R www-data:www-data var

echo "→ Attente de PostgreSQL (${POSTGRES_HOST:-postgres})…"
until pg_isready -h "${POSTGRES_HOST:-postgres}" -U "${POSTGRES_USER:-vitegourmand}" -q; do
    sleep 1
done

echo "→ Migrations Doctrine…"
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

if [ "${SEED_DEMO:-1}" = "1" ]; then
    echo "→ Données de démonstration…"
    php bin/console app:seed-demo
fi

if [ "${SYNC_MONGO_ON_START:-1}" = "1" ]; then
    echo "→ Synchronisation MongoDB depuis PostgreSQL…"
    php bin/console app:sync-mongo || echo "⚠ MongoDB indisponible : l'API utilisera le fallback SQL."
fi

php bin/console cache:clear --no-warmup > /dev/null
chown -R www-data:www-data var

exec docker-php-entrypoint "$@"
