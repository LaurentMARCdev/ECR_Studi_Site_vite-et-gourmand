# ═══════════════════════════════════════════════════════════════════
# Image de l'application Vite & Gourmand
# ───────────────────────────────────────────────────────────────────
# Reproduit la stack de production Alwaysdata :
#   - PHP 8.2 avec Apache intégré
#   - PostgreSQL 16 (extension pdo_pgsql)
#   - MongoDB 7 (extension ext-mongodb v1.21 fixée)
#   - Composer 2
# ═══════════════════════════════════════════════════════════════════
FROM php:8.2-apache

# ─── Variables d'env pour l'installation ────────────────────────────
# COMPOSER_ALLOW_SUPERUSER=1  Sans ça, Composer désactive ses plugins et
# vendor/autoload_runtime.php n'est pas généré, Symfony ne démarre pas.
ENV COMPOSER_ALLOW_SUPERUSER=1

# ─── Paquets système requis ─────────────────────────────────────────
#   - git, unzip : composer télécharge en .zip et peut cloner des sources
#   - libpq-dev : headers PostgreSQL pour compiler pdo_pgsql
#   - libicu-dev : pour intl (collation, formatage i18n)
#   - libzip-dev : pour l'extension zip
#   - libssl-dev : requis par ext-mongodb
#   - postgresql-client : fournit pg_isready utilisé dans entrypoint.sh
#   - $PHPIZE_DEPS : outils de compilation nécessaires pour pecl install
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libpq-dev \
        libicu-dev \
        libzip-dev \
        libssl-dev \
        postgresql-client \
        $PHPIZE_DEPS \
    && rm -rf /var/lib/apt/lists/*

# ─── Extensions PHP compilées ───────────────────────────────────────
# intl + pdo_pgsql + zip + opcache
RUN docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        intl \
        zip \
        opcache

# ─── Extension MongoDB (version fixée) ──────────────────────────────
# La version doit matcher celle du composer.lock (mongodb/mongodb 1.21 exige ext-mongodb ^1.21).
# Une version plus ancienne fait échouer `composer install`.
RUN pecl install mongodb-1.21.0 \
    && docker-php-ext-enable mongodb

# ─── Composer ───────────────────────────────────────────────────────
# Copie depuis l'image officielle Composer 2 (évite d'installer PHP + wget)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ─── Modules Apache ─────────────────────────────────────────────────
# rewrite : pour les règles .htaccess du front qui routent /api/* vers Symfony
# headers : pour les headers CORS et sécurité (CSP, HSTS en prod)
RUN a2enmod rewrite headers

# ─── Configuration Apache (vhost custom) ────────────────────────────
# Vhost remplace le défaut Apache pour servir le front
# et rediriger les requêtes API vers Symfony
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# ─── Dossier de travail ─────────────────────────────────────────────
# /var/www/api : où est l'app Symfony.
WORKDIR /var/www/api

# ─── Copie des sources ──────────────────────────────────────────────
# En dev, un volume dans docker-compose.yml écrasera ces fichiers
# pour permettre le hot reload des modifications.
COPY api/ /var/www/api/
COPY front/ /var/www/front/

# ─── Installation des dépendances PHP ───────────────────────────────
# --no-interaction : pas de prompts (indispensable en Docker)
# --prefer-dist : télécharge en .zip (plus rapide que git clone)
# --no-scripts : évite d'exécuter cache:clear avant que l'env soit prêt
#                (c'est l'entrypoint qui s'en chargera au démarrage)
RUN composer install --no-interaction --prefer-dist --no-scripts

# ─── Entrypoint ─────────────────────────────────────────────────────
# Script lancé au démarrage du conteneur. Gère :
#   1. Composer install si vendor manquant (cas des volumes montés)
#   2. Attente de PostgreSQL (évite les erreurs de connexion au boot)
#   3. Migrations Doctrine automatiques
#   4. Démarrage d'Apache
COPY docker/entrypoint.sh /usr/local/bin/vg-entrypoint
RUN chmod +x /usr/local/bin/vg-entrypoint
ENTRYPOINT ["vg-entrypoint"]