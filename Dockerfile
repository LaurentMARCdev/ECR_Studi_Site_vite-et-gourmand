# ═══════════════════════════════════════════════════════════════
# Vite & Gourmand — image de l'application (front + API Symfony)
# ═══════════════════════════════════════════════════════════════
# PHP 8.2 + Apache, avec les extensions exigées par composer.json :
#   pdo_pgsql (PostgreSQL), mongodb (NoSQL), intl, zip, opcache
#   (mbstring, ctype, iconv, xml et openssl sont déjà inclus dans l'image officielle)
#   La version de l'extension mongodb est figée en 1.21 : la bibliothèque
#   mongodb/mongodb 1.21 du composer.lock exige ext-mongodb ^1.21 (la 2.x est refusée).
#
# Apache sert le front statique (front/) comme racine du site et
# délègue /api/* au contrôleur frontal Symfony (api/public/index.php),
# exactement comme en production sur Alwaysdata.
# ═══════════════════════════════════════════════════════════════
FROM php:8.2-apache

# ── Paquets système + extensions PHP ──────────────────────────
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libicu-dev libzip-dev libssl-dev postgresql-client $PHPIZE_DEPS \
 && docker-php-ext-install pdo_pgsql intl zip opcache \
 && pecl install mongodb-1.21.0 \
 && docker-php-ext-enable mongodb \
 && apt-get purge -y --auto-remove $PHPIZE_DEPS \
 && rm -rf /var/lib/apt/lists/* /tmp/pear

# ── Composer ──────────────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
# En root et sans interaction, Composer désactive ses plugins ; symfony/runtime
# en est un et génère vendor/autoload_runtime.php, indispensable à index.php.
ENV COMPOSER_ALLOW_SUPERUSER=1

# ── Configuration PHP et Apache ───────────────────────────────
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-vitegourmand.ini
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite headers

# ── Code de l'application ─────────────────────────────────────
# En développement, docker-compose monte api/ et front/ par-dessus
# (modifications visibles sans reconstruire l'image).
WORKDIR /var/www/api
COPY api/ /var/www/api/
COPY front/ /var/www/front/
RUN composer install --no-interaction --prefer-dist --no-scripts --no-progress \
 && mkdir -p var && chown -R www-data:www-data var

COPY docker/entrypoint.sh /usr/local/bin/vg-entrypoint
RUN chmod +x /usr/local/bin/vg-entrypoint

EXPOSE 80
ENTRYPOINT ["vg-entrypoint"]
CMD ["apache2-foreground"]
