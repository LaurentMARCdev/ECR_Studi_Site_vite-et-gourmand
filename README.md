# 🍽️ Vite & Gourmand
[![forthebadge](http://forthebadge.com/images/badges/built-with-love.svg)](http://forthebadge.com)
> Plateforme web full-stack pour un traiteur artisanal bordelais.
> Projet réalisé dans le cadre de l'**Épreuve de Certification Finale** du titre professionnel **Développeur Web et Web Mobile (DWWM)** — Studi.

[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-7-000000?logo=symfony&logoColor=white)](https://symfony.com/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-15-4169E1?logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![MongoDB](https://img.shields.io/badge/MongoDB-6-47A248?logo=mongodb&logoColor=white)](https://www.mongodb.com/)
[![Tailwind](https://img.shields.io/badge/Tailwind-CSS-06B6D4?logo=tailwind-css&logoColor=white)](https://tailwindcss.com/)
[![Alpine](https://img.shields.io/badge/Alpine.js-3-8BC0D0?logo=alpine.js&logoColor=white)](https://alpinejs.dev/)

---

## 🌍 Démo en ligne

**🔗 [https://vitegourmandlm.alwaysdata.net](https://vitegourmandlm.alwaysdata.net)**

Site déployé en production sur Alwaysdata (Paris) avec HTTPS Let's Encrypt et base PostgreSQL 17.

---

## 🎯 Contexte

Julie et José, 25 ans, lancent leur activité de traiteur artisanal à Bordeaux sous la marque **Vite & Gourmand**. Cette plateforme leur permet de présenter leurs menus, de gérer les commandes en ligne, de traiter la logistique côté employés et de piloter l'activité côté administration.

---

## ✨ Fonctionnalités

### 👤 Côté client
- Consultation des menus avec **5 filtres dynamiques** (thème, régime, prix, nombre de personnes, disponibilité)
- Inscription / connexion sécurisée (Argon2id, réinitialisation mot de passe)
- Passage de commande avec calculs automatiques (réduction 10% si commande > 500€, frais de livraison Bordeaux/hors Bordeaux)
- Suivi de commande en temps réel via timeline visuelle
- Dépôt d'avis après livraison
- Formulaire de contact avec rate limiting

### 👥 Côté employé
- Tableau **Kanban** des commandes (glisser-déposer entre les 8 statuts)
- Gestion des menus et plats (CRUD complet, soft delete)
- Modération des avis (validation / rejet motivé)
- Gestion des horaires d'ouverture

### 👑 Côté administrateur
- Toutes les fonctionnalités employé
- **Formulaire de création de plats** avec catégorisation (entrée / plat principal / dessert) et multi-sélection d'allergènes
- **Formulaire de création de menus** composables avec plats groupés par catégorie, thèmes et régimes multi-sélectionnables
- Création de comptes employés (avec envoi de mail pour définition du mot de passe)
- **Tableau de bord statistiques** alimenté par MongoDB (avec fallback SQL automatique)
- CA par menu, commandes par période, note moyenne, top clients

---

## 🛠️ Stack technique

| Couche | Technologie |
|--------|-------------|
| Back-end | Symfony 7 · PHP 8.2 |
| BDD relationnelle | PostgreSQL 15+ (17 en production) |
| BDD NoSQL | MongoDB 6 *(avec fallback SQL)* |
| ORM | Doctrine 3 |
| Front-end | HTML5 · Tailwind CSS · Alpine.js |
| Auth | Sessions PHP · Cookies HttpOnly · Argon2id |
| Emails | Symfony Mailer · 8 templates Twig |
| Hébergement | Alwaysdata (Paris) · HTTPS Let's Encrypt |

---

## 📁 Structure du dépôt

```
vite-gourmand/
├── api/                        # Back-end Symfony 7
│   ├── src/
│   │   ├── Controller/         # 15 contrôleurs · 51 endpoints
│   │   ├── Service/            # 15 services métier
│   │   ├── Entity/             # 14 entités Doctrine
│   │   ├── Repository/         # 12 repositories
│   │   ├── DTO/                # 13 objets de transfert
│   │   ├── Security/           # Authenticator custom
│   │   └── EventListener/      # Sync MongoDB automatique
│   ├── config/
│   ├── migrations/             # 5 migrations Doctrine + SQL brut
│   ├── templates/emails/       # 8 templates transactionnels
│   └── README.md               # Doc technique du back-end
│
├── front/                      # Front-end statique
│   ├── index.html              # Page d'accueil
│   ├── menus.html              # Catalogue avec filtres
│   ├── menu-detail.html        # Fiche menu détaillée
│   ├── commande.html           # Formulaire de commande
│   ├── connexion.html          # Inscription / connexion
│   ├── contact.html            # Formulaire de contact
│   ├── mon-espace.html         # Espace client
│   ├── employe.html            # Interface employé
│   ├── admin.html              # Back-office admin
│   └── .htaccess               # Routing Apache (rewrites)
│
├── docs/                       # Livrables documentaires ECF
│   ├── VG-ManuelUtilisateur.pdf       (17 pages)
│   ├── VG-DocumentationTechnique.pdf  (21 pages)
│   ├── VG-CharteGraphique.pdf         (14 pages)
│   ├── VG-GestionDeProjet.pdf         (17 pages)
│   └── VG-Deploiement.pdf             (24 pages)
│
└── README.md                   # Ce fichier
```

---

## 🌿 Organisation Git

Le projet utilise un workflow **GitFlow simplifié** :

- **`main`** — branche de production, contient uniquement du code stable et déployé.
- **`develop`** — branche de développement, reçoit les évolutions avant fusion vers `main`.

Ce workflow permet de garder `main` toujours dans un état déployable, tout en accueillant les modifications de dernière minute et les tests sur `develop`.

---

## 🚀 Installation locale

### Prérequis
- **PHP 8.2+** avec extensions : `pdo_pgsql`, `mongodb`, `mbstring`, `intl`, `openssl`
- **Composer 2**
- **PostgreSQL 15+**
- **MongoDB 6+** *(optionnel — fallback SQL disponible)*
- **Symfony CLI** *(optionnel mais pratique)*

### Étapes

```bash
# 1. Cloner le dépôt
git clone https://github.com/LaurentMARCdev/ECR_Studi_Site_vite-et-gourmand.git
cd ECR_Studi_Site_vite-et-gourmand/api

# 2. Installer les dépendances PHP
composer install

# 3. Configurer les variables d'environnement
cp .env .env.local
# Éditer .env.local et renseigner :
#   DATABASE_URL="postgresql://user:pwd@127.0.0.1:5432/vitegourmand"
#   MONGODB_URL="mongodb://127.0.0.1:27017" (optionnel)
#   MAILER_DSN="smtp://mailhog:1025" (dev) ou "smtp://<brevo>" (prod)

# 4. Créer la base et exécuter les migrations
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction

# 5. Initialiser MongoDB (optionnel)
mongosh mongodb://127.0.0.1:27017 --file migrations/mongo/004_mongo_init.js

# 6. Créer le compte administrateur
php bin/console app:create-admin \
    --email admin@vitegourmand.fr \
    --password 'Admin@Vite2025'

# 7. Lancer le serveur de dev
symfony server:start
# ou : php -S 127.0.0.1:8000 -t public
```

L'API tourne alors sur `http://127.0.0.1:8000`.

### Comptes de test

Ces comptes sont disponibles sur la démo en ligne :

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| Client | `client@vitegourmand.fr` | `Client@Test2025` |
| Employé | `employe@vitegourmand.fr` | `Employe@Test2025` |

Les identifiants administrateur sont fournis uniquement dans le document rendu sur Studi, pour des raisons de sécurité.

---

## 🌐 Déploiement en production

Deux stratégies documentées dans [`docs/VG-Deploiement.pdf`](docs/VG-Deploiement.pdf) :

### Option A — VPS + Docker (production robuste)
Ubuntu 22.04 · Nginx · Docker Compose · Let's Encrypt · CI/CD GitHub Actions · Backups S3-compatible.

### Option B — Alwaysdata (choix retenu pour la démo ECF) ✅
Hébergement français (Paris), gratuit, sans carte bancaire. Configuration en ~30 min via l'interface d'administration.

**Site en production** : [https://vitegourmandlm.alwaysdata.net](https://vitegourmandlm.alwaysdata.net)

Le déploiement utilise le fallback SQL pour se passer de MongoDB en production, tout en conservant le code MongoDB dans le dépôt pour démontrer la compétence NoSQL. Le routing Apache (`.htaccess`) gère les URLs propres (`/menus/{id}`, `/commande`) et le fallback vers Symfony pour les endpoints API.

---

## 📚 Livrables documentaires ECF

Les cinq documents PDF de l'ECF sont disponibles dans le dossier [`docs/`](docs/) :

| Livrable | Contenu | Pages |
|----------|---------|-------|
| [Manuel utilisateur](docs/VG-ManuelUtilisateur.pdf) | Parcours des 3 profils, écrans, comptes de test, FAQ | 17 |
| [Documentation technique](docs/VG-DocumentationTechnique.pdf) | Architecture, MCD/MLD/dictionnaire, UML, API, sécurité, règles métier | 21 |
| [Charte graphique](docs/VG-CharteGraphique.pdf) | Identité, palette, typographie, composants, 6 maquettes bureau + mobile | 14 |
| [Gestion de projet](docs/VG-GestionDeProjet.pdf) | Cadrage SMART/MoSCoW, sprints Scrum, GANTT, risques, bilan | 17 |
| [Documentation de déploiement](docs/VG-Deploiement.pdf) | Architecture cible, procédure pas-à-pas, CI/CD, sécurité, backups | 24 |

**Total : 93 pages de documentation professionnelle.**

---

## 🎨 Aperçu visuel

Palette signature :

| Couleur | Code | Usage |
|---------|------|-------|
| 🟫 Nuit | `#1A1208` | Fond sombre, texte principal |
| 🟨 Crème | `#F5F0E8` | Fond clair, texte sur fond sombre |
| 🟧 Rouille | `#D4521A` | Accent principal, CTA |
| 🟩 Herbe | `#4A7C59` | Feedback positif |
| 🟨 Safran | `#F0C060` | Ratings, dégradés |

Typographie : **Playfair Display** (titres) + **Inter** (corps).

---

## 🔒 Sécurité

- Hashage des mots de passe **Argon2id** (recommandation ANSSI)
- Politique de mot de passe stricte (10 caractères min., MAJ + min + chiffre + spécial)
- Tokens de réinitialisation **hashés SHA-256** en base
- **Rate limiting** sur login (5/min), reset (3/15min), contact (5/15min)
- Cookies **HttpOnly + SameSite=Lax + Secure**
- Format public RGPD des avis (prénom + initiale du nom uniquement)
- Contraintes d'intégrité PostgreSQL (CHECK, UNIQUE, FK) en filet de sécurité

---

## 🧪 Points techniques notables

- **Verrou pessimiste Doctrine** sur la décrémentation du stock (protection race condition)
- **Fallback SQL automatique** si MongoDB indisponible — l'API reste opérationnelle
- **Listener Doctrine** `postPersist` / `postUpdate` pour la synchronisation MongoDB en arrière-plan
- **Migrations Doctrine PHP + scripts SQL bruts** en parallèle (double filet de sécurité)
- **8 templates Twig** transactionnels (confirmation, changement de statut, invitation avis…)
- **Enums PHP 8.2** pour tous les référentiels fermés (statuts, rôles, catégories)
- Numérotation unique des commandes : format `VG-YYYY-NNNN`
- **Formulaires composables** côté admin : création dynamique de plats et de menus avec chargement des référentiels (thèmes, régimes, allergènes) depuis l'API

---

## 📖 Documentation API

**51 endpoints REST** documentés dans [`docs/VG-DocumentationTechnique.pdf`](docs/VG-DocumentationTechnique.pdf), section 5.

Répartition par domaine :
- 🔓 Auth (5 endpoints)
- 🍽️ Menus publics (3 endpoints)
- 📖 Référentiels (3 endpoints)
- 👤 Utilisateur (4 endpoints)
- 🛒 Commandes (2 endpoints)
- ⭐ Avis (2 endpoints)
- 👥 Employé (14 endpoints)
- 👑 Admin (7 endpoints)
- 📞 Contact (1 endpoint)
- 🕐 Horaires publics (1 endpoint)
- 📊 Stats (2 endpoints)
- 🧾 Autres (7 endpoints)

Une collection Postman est également disponible dans [`api/postman/`](api/postman/) pour tester l'ensemble des endpoints.

---

## 👤 Auteur

**Laurent MARC** — Étudiant Studi, formation *Développeur Web et Web Mobile*.

Projet réalisé dans le cadre de l'**Épreuve de Certification Finale** (ECF).

---

## 📄 Licence

Projet pédagogique — Tous droits réservés.
Le contexte fictif *Vite & Gourmand* et son identité graphique sont créés à des fins de démonstration dans le cadre de la certification.

---

## 🔮 Prochains développements à prévoir

- Incorporer davantage de photographies pour rendre cette version moins impersonnelle.
- Travailler sur de nouvelles mesures de sécurité pour rendre l'ensemble plus résilient.
- Édition de menus : pré-remplir la liste des plats sélectionnés lors de la modification (actuellement, l'édition d'un menu nécessite de re-cocher les plats).
- Intégrer un système de paiement en ligne (Stripe ou équivalent) pour compléter le workflow de commande.
- Migrer les warnings de dépréciation Symfony 7.3/7.4 vers les nouvelles API recommandées.
