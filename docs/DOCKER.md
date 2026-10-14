# Environnement conteneurisé (Docker)

Ce document décrit l'environnement de développement conteneurisé de Vite & Gourmand
et explique pourquoi la production reste sur Alwaysdata.

## Ce que lance `docker compose`

| Service | Image | Rôle | Port hôte |
|---|---|---|---|
| `app` | construite depuis le `Dockerfile` (PHP 8.2 + Apache) | front statique + API Symfony | `8080` |
| `postgres` | `postgres:16-alpine` | base relationnelle (données métier) | — |
| `mongo` | `mongo:7` | base NoSQL (statistiques des commandes) | — |
| `mongo-express` | `mongo-express:1` | interface web MongoDB (profil `outils`, facultatif) | `8081` |

L'image `app` contient PHP 8.2 avec les extensions `pdo_pgsql`, `mongodb` (1.21), `intl`,
`zip` et `opcache`, Apache avec `mod_rewrite`, et Composer 2.
Le vhost Apache (`docker/apache/vhost.conf`) reproduit la configuration de production :
le dossier `front/` est servi à la racine et les URL `/api/*` sont routées vers Symfony.

Au démarrage, le script `docker/entrypoint.sh` :
1. installe les dépendances Composer si besoin ;
2. attend PostgreSQL puis exécute les migrations Doctrine ;
3. crée les données de démonstration (`app:seed-demo`) si `SEED_DEMO=1` ;
4. synchronise MongoDB depuis PostgreSQL (`app:sync-mongo`) si `SYNC_MONGO_ON_START=1`.

MongoDB est initialisé par `api/migrations/mongo/004_mongo_init.js` (collection, index,
validateur JSON Schema), monté dans `/docker-entrypoint-initdb.d`.

## Démarrage

Prérequis : Docker Desktop (ou Docker Engine + plugin Compose).

```bash
cp .env.example .env        # puis remplacer les mots de passe "changeme"
docker compose up -d --build
docker compose logs -f app  # attendre "Apache ... resuming normal operations"
```

Le site est disponible sur <http://localhost:8080>.

Le fichier `.env` n'est **jamais versionné** (voir `.gitignore`) : chacun y met ses propres
secrets. Docker Compose refuse de démarrer si une variable obligatoire manque.

### Comptes de démonstration

Créés par `app:seed-demo` avec les mots de passe définis dans `.env` :

| Rôle | E-mail | Variable du mot de passe |
|---|---|---|
| Client | `client@vitegourmand.fr` | `DEMO_PASSWORD_CLIENT` |
| Employé | `employe@vitegourmand.fr` | `DEMO_PASSWORD_EMPLOYE` |
| Administrateur | `admin@vitegourmand.fr` | `DEMO_PASSWORD_ADMIN` |

Le jeu de démonstration contient 4 menus, 9 plats, une commande dans chacun des 8 états
du cycle de vie et des avis (un publié, un en attente de modération).

## Démontrer l'accès réel à MongoDB

Le back-end écrit dans MongoDB à chaque création ou modification de commande
(`CommandeStatisticsListener`), et le tableau de bord administrateur lit ses statistiques
dans MongoDB (`StatistiquesService`, avec repli SQL si MongoDB est indisponible).

```bash
# Resynchroniser la collection depuis PostgreSQL puis la relire
docker compose exec app php bin/console app:sync-mongo

# Interroger MongoDB directement
docker compose exec mongo sh -c 'mongosh -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" \
  --authenticationDatabase admin vitegourmand_stats --quiet \
  --eval "db.statistiques_commandes.aggregate([{\$group:{_id:\"\$menu_titre\", n:{\$sum:1}, ca:{\$sum:\"\$prix_total\"}}}])"'

# Interface web (http://localhost:8081, identifiants MONGO_EXPRESS_* du .env)
docker compose --profile outils up -d mongo-express
```

Scénario de démonstration : passer une commande avec le compte client, constater dans
mongosh qu'un document est apparu, puis voir le graphique « Commandes par menu » de
l'espace administrateur évoluer. En arrêtant le conteneur (`docker compose stop mongo`),
le tableau de bord continue de fonctionner grâce au repli SQL, et `app:sync-mongo`
signale proprement que MongoDB est injoignable.

## Commandes utiles

```bash
docker compose exec app php bin/console doctrine:migrations:migrate
docker compose exec app php bin/console app:seed-demo --force   # recrée les données de démo
docker compose exec postgres sh -c 'psql -U "$POSTGRES_USER" "$POSTGRES_DB"'
docker compose down          # arrêter (les données sont conservées)
docker compose down -v       # arrêter et effacer les volumes (bases remises à zéro)
```

Les dossiers `api/` et `front/` sont montés dans le conteneur : une modification du code
est visible immédiatement, sans reconstruire l'image.

## Pourquoi la production reste sur Alwaysdata

La production est hébergée sur Alwaysdata (offre gratuite, Paris) :

- **Coût** : l'offre gratuite suffit pour la démonstration de l'ECF ; un hébergement
  capable d'exécuter des conteneurs (VPS, PaaS Docker) est payant.
- **Périmètre** : l'offre gratuite d'Alwaysdata ne permet pas d'exécuter Docker ni MongoDB.
  En production, les statistiques utilisent donc le repli SQL déjà prévu dans le code.
- **Objectif** : la conteneurisation sert ici à fournir un environnement de développement
  reproductible (mêmes versions de PHP, PostgreSQL et MongoDB pour tous) et à démontrer
  l'intégration NoSQL sans dépendre d'un service externe comme MongoDB Atlas.

La même configuration permettrait un déploiement conteneurisé ailleurs (VPS, Scaleway,
OVHcloud, Render…) : l'image `app` est autonome, et il suffirait de fournir un `.env` de
production (`APP_ENV=prod`, secrets forts, `MAILER_DSN` réel), de ne pas monter le code
en volume, de placer un reverse proxy HTTPS (Nginx/Caddy/Traefik) devant le port 80 et de
sauvegarder les volumes `pgdata` et `mongodata`.

## Déploiement Alwaysdata : point d'attention

Le JavaScript du site est désormais dans des fichiers dédiés (`front/js/`) et les images
de secours dans `front/media/`. Lors d'une mise en production, il faut donc copier
l'ensemble du dossier `front/` (HTML, `js/`, `media/`, `.htaccess`) et pas seulement
les fichiers `*.html`.
