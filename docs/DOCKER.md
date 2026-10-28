# Dockerisation de Vite & Gourmand

Ce document décrit la stack Docker fournie pour l'application
Vite & Gourmand, les commandes utiles au quotidien, et les choix
d'architecture.

## 📦 Services orchestrés

Le `docker-compose.yml` monte 3 services principaux + 1 outil optionnel.

### `app` — Serveur applicatif
- Image construite à partir du `Dockerfile` à la racine
- PHP 8.2 + Apache 2.4
- Extensions : `pdo_pgsql`, `intl`, `zip`, `opcache`, `mongodb` (v1.21)
- Expose le port **8080** (mappé sur 80 interne)
- Monte les sources `api/` et `front/` en volumes pour permettre
  les modifications sans rebuild (mode développement)

### `postgres` — Base relationnelle
- Image officielle `postgres:16-alpine`
- Volume nommé `pgdata` pour persistance
- Healthcheck via `pg_isready`

### `mongo` — Base NoSQL
- Image officielle `mongo:7`
- Volume nommé `mongodata` pour persistance
- Script d'init `004_mongo_init.js` exécuté au premier démarrage
- Healthcheck via `mongosh adminCommand('ping')`

### `mongo-express` (profile `outils`) — UI MongoDB
- Non lancé par défaut
- Pour l'activer : `docker compose --profile outils up`
- Expose le port **8081** pour inspecter la base NoSQL dans un navigateur

## 🚀 Installation et démarrage

### Prérequis
- Docker Engine 24+ ou Docker Desktop récent
- Windows 10 22H2+, Windows 11, macOS 10.15+ ou Linux
- 4 Go RAM minimum, 8 Go recommandés
- WSL2 activé si sur Windows

### Mise en route

```bash
# 1. Cloner le dépôt
git clone https://github.com/LaurentMARCdev/ECR_Studi_Site_vite-et-gourmand.git
cd ECR_Studi_Site_vite-et-gourmand

# 2. Copier le fichier d'environnement d'exemple
cp .env.example .env

# 3. Éditer .env et renseigner les valeurs réelles
#    - APP_SECRET : généré avec php -r "echo bin2hex(random_bytes(32));"
#    - Mots de passe PostgreSQL et MongoDB

# 4. Lancer la stack
docker compose up -d --build

# 5. Vérifier que les services sont healthy
docker compose ps
# Les 3 services doivent être "running / healthy" après ~30-60 secondes
```

L'application est alors accessible sur **http://localhost:8080**.

## 🔧 Commandes utiles

```bash
# Logs d'un service
docker compose logs -f app
docker compose logs -f postgres

# Shell dans le conteneur app
docker compose exec app bash

# Commandes Symfony
docker compose exec app php bin/console cache:clear
docker compose exec app php bin/console doctrine:migrations:status
docker compose exec app php bin/console app:sync-mongo

# Arrêter tous les services (données conservées)
docker compose down

# Arrêter ET supprimer les volumes (⚠️ perte des données)
docker compose down -v

# Reconstruire une image après modification du Dockerfile
docker compose build app
```

## 🍃 Démontrer le fonctionnement de MongoDB

L'application utilise MongoDB pour stocker des statistiques agrégées
par menu. Un `CommandeStatisticsListener` écrit dans MongoDB à chaque
commande créée, et `StatistiquesService` lit depuis MongoDB avec un
**repli automatique sur SQL** si MongoDB est indisponible.

### Scénario de test

```bash
# 1. Vérifier que MongoDB contient des documents après seed
docker compose exec app php bin/console app:sync-mongo
docker compose exec mongo mongosh \
  -u "$MONGO_ROOT_USER" -p "$MONGO_ROOT_PASSWORD" \
  --authenticationDatabase admin \
  vitegourmand_stats \
  --eval "db.statistiques_commandes.countDocuments()"

# 2. Passer une nouvelle commande via l'interface web, puis relancer
#    la requête : le compteur augmente d'une unité.

# 3. Tester la résilience : arrêter MongoDB
docker compose stop mongo

# Le tableau de bord admin continue de fonctionner grâce au repli SQL
# dans StatistiquesService.
```

## 🌐 Choix de l'hébergement de production

### Pourquoi Alwaysdata plutôt que Docker en prod ?

La production utilise **Alwaysdata** (offre gratuite) et non une
stack Docker, pour les raisons suivantes :

- **Coût** : l'offre gratuite d'Alwaysdata couvre les besoins actuels
  (PHP 8.2, PostgreSQL, 100 Mo de stockage, 1 base MySQL/Postgres,
  domaine `.alwaysdata.net` fourni)
- **Simplicité** : pas d'infrastructure à maintenir, sauvegardes
  automatiques côté hébergeur
- **Pas de Docker ni de MongoDB** chez Alwaysdata : la fonctionnalité
  stats MongoDB tourne donc en mode "repli SQL" en production (sans
  dégradation visible pour les utilisateurs)
- **Un VPS Docker aurait un coût** mensuel (5-10 € minimum) pour
  un bénéfice limité à ce stade du projet
- **Absence de test en prod** La configuration de l'ordinateur en 
  phase de développement n'a pas permis de tester le docker, il 
  aurait aussi été risqué de pousser en production sans cette phase
  de validation.
    
### Portabilité

La stack Docker fournie permet néanmoins un déploiement conteneurisé
sur un VPS Linux à tout moment, par exemple :

- OVH VPS, Scaleway Stardust, DigitalOcean Droplet
- Avec un reverse proxy HTTPS (Traefik, Caddy, nginx)
- Un `.env` de production avec de vrais secrets

Les mêmes `Dockerfile` et `docker-compose.yml` sont utilisables tels
quels, moyennant la configuration des variables d'environnement.

## ⚠️ Note sur l'environnement de développement de l'auteur

L'auteur développe sur Windows 10 Home 21H2, une version du système
d'exploitation qui ne supporte plus les versions récentes de Docker
Desktop (minimum requis : Windows 10 Home 22H2).

Les fichiers de la stack Docker (`Dockerfile`, `docker-compose.yml`,
`entrypoint.sh`, `vhost.conf`) ont donc été rédigés conformément aux
bonnes pratiques Docker, Symfony et PostgreSQL, mais **n'ont pas pu
être validés par une exécution complète sur le poste de développement**.
Ils restent fonctionnels sur n'importe quel environnement Linux ou
Windows à jour disposant de Docker Engine.

En contexte professionnel, cette limitation serait levée en mettant
à jour le poste de travail ou en travaillant directement sur une VM
Linux de développement.