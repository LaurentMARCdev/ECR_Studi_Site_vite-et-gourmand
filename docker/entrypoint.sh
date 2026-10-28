#!/bin/bash
# ═══════════════════════════════════════════════════════════════════
# Script d'entrée du conteneur app
# ───────────────────────────────────────────────────────────────────
# Exécuté à chaque démarrage du conteneur. Gère :
#   1. Composer install si vendor manquant (cas des volumes montés en dev)
#   2. Attente de PostgreSQL (évite les erreurs de connexion au boot)
#   3. Migrations Doctrine automatiques
#   4. (Optionnel) Chargement des données de démo
#   5. Démarrage d'Apache en avant-plan
# ═══════════════════════════════════════════════════════════════════

set -e

# ─── 1. Composer install si vendor absent ──────────────────────────
# En dev, le volume /var/www/api monté depuis l'hôte peut écraser
# le vendor/ créé au build. On réinstalle alors.
if [ ! -f /var/www/api/vendor/autoload_runtime.php ]; then
    echo "📦 vendor/ absent — composer install en cours..."
    cd /var/www/api && composer install --no-interaction --prefer-dist --no-scripts
fi

# ─── 2. Attente de PostgreSQL ──────────────────────────────────────
# L'app ne doit pas démarrer avant que la base soit prête à répondre,
# sinon les migrations échouent avec "connection refused".
# postgresql-client fournit pg_isready qui teste la dispo réseau.
echo "⏳ Attente de PostgreSQL..."
until pg_isready -h "${POSTGRES_HOST:-postgres}" -p 5432 -U "${POSTGRES_USER:-vitegourmand}" > /dev/null 2>&1; do
    echo "   ... PostgreSQL n'est pas prêt, nouvelle tentative dans 2s"
    sleep 2
done
echo "✅ PostgreSQL est prêt"

# ─── 3. Migrations Doctrine ────────────────────────────────────────
# --no-interaction : pas de prompts, --allow-no-migration : OK si rien
echo "🔄 Application des migrations..."
cd /var/www/api
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# ─── 4. Seed de démo (optionnel) ───────────────────────────────────
# Activé via la variable SEED_DEMO=1 dans l'environnement.
if [ "${SEED_DEMO:-0}" = "1" ]; then
    echo "🌱 Chargement des données de démo..."
    php bin/console app:seed-demo --no-interaction || true
fi

# ─── 5. Démarrage d'Apache ─────────────────────────────────────────
# docker-php-entrypoint lance apache2-foreground, qui garde le
# conteneur vivant en bloquant sur le processus Apache.
# exec remplace le processus bash par Apache (PID 1 propre, signaux OK).
echo "🚀 Démarrage d'Apache..."
exec docker-php-entrypoint apache2-foreground