# Audit de sécurité des dépendances

**Projet** : Vite & Gourmand — Traiteur artisanal bordelais
**Date de l'audit** : 21 octobre 2026
**Outil utilisé** : `composer audit` (Composer 2.x)
**Rapport brut** : [audit-securite-2026-10-21.txt](./audit-securite-2026-10-21.txt)

---

## Synthèse

L'audit a identifié **10 vulnérabilités** réparties sur **6 packages** du projet. Après analyse d'impact sur la stack applicative réelle, aucune de ces vulnérabilités ne constitue un risque critique immédiat pour le site en production.

Le patch des dépendances est planifié en phase de maintenance post-ECF selon la procédure décrite en fin de document.

---

## Contexte technique

| Composant | Version | Statut sécurité |
|-----------|---------|-----------------|
| PHP (web) | 8.4.27 | ✅ À jour (patchs auto Alwaysdata) |
| Symfony | 7.x | ⚠️ Vulnérabilités mineures identifiées |
| Doctrine ORM | 3.x | ✅ Pas de vulnérabilité |
| MongoDB driver | installé mais inactif (fallback SQL) | ⚠️ Vulnérabilités identifiées |
| Système d'exploitation | Debian (Alwaysdata) | ✅ Maintenu par l'hébergeur |

---

## Vulnérabilités identifiées

### 🔴 Haute priorité (2 CVE)

#### CVE-2026-81525 — mongodb/mongodb (severity HIGH)

- **Nature** : rejet insuffisant des caractères `.` et NUL bytes dans les noms de bases et collections
- **Impact réel** : limité — MongoDB n'est pas actif en production (fallback SQL automatique), mais le package est chargé en mémoire
- **Action planifiée** : mise à jour vers mongodb/mongodb >= 2.4.1

#### CVE-2026-45068 — symfony/mailer (severity MEDIUM)

- **Nature** : injection d'arguments dans le `SendmailTransport` via des adresses de destinataire commençant par `-`
- **Impact réel** : nul — le projet utilise un transport SMTP (DSN `smtp://...@smtp-vitegourmandlm.alwaysdata.net`), pas Sendmail
- **Action planifiée** : mise à jour vers symfony/mailer >= 7.4.12 par hygiène

### 🟡 Priorité modérée (4 CVE)

#### CVE-2026-108082 — symfony/http-foundation

- **Nature** : ReDoS (Regular Expression Denial of Service) dans `HeaderUtils::split()` via le header `Cache-Control`
- **Impact réel** : modéré — un attaquant pourrait solliciter anormalement le serveur avec des headers forgés
- **Action planifiée** : mise à jour vers symfony/http-foundation >= 7.4.19

#### CVE-2024-50340 — symfony/runtime

- **Nature** : possibilité de changer d'environnement d'exécution via un paramètre de requête
- **Impact réel** : limité — le fichier `.env.local` force `APP_ENV=prod` et `APP_DEBUG=0`
- **Action planifiée** : mise à jour vers symfony/runtime >= 7.1.7

#### CVE-2026-109413 — symfony/yaml

- **Nature** : récursion non bornée dans le lexer YAML inline permettant de crasher le processus
- **Impact réel** : quasi nul — YAML utilisé uniquement en configuration interne, pas d'input utilisateur
- **Action planifiée** : mise à jour vers symfony/yaml >= 7.4.18

#### CVE-2026-88023 — mongodb/mongodb (GridFS)

- **Nature** : divulgation et suppression de données GridFS via injection d'opérateurs de requête
- **Impact réel** : nul — GridFS non utilisé dans le projet
- **Action planifiée** : patché automatiquement avec la mise à jour mongodb/mongodb

### 🟢 Basse priorité (4 CVE)

| CVE | Package | Nature | Impact réel |
|-----|---------|--------|-------------|
| CVE-2024-50343 | symfony/validator | Réponse incorrecte en fin d'entrée | Très faible |
| CVE-2026-45304 | symfony/yaml | "Billion Laughs" via alias récursifs | Nul (pas d'input YAML utilisateur) |
| CVE-2026-45305 | symfony/yaml | ReDoS via backtracking catastrophique | Nul (pas d'input YAML utilisateur) |
| CVE-2026-45133 | symfony/yaml | Épuisement de pile par récursion imbriquée | Nul (pas d'input YAML utilisateur) |

---

## Vulnérabilités non applicables à notre stack

Pour mémoire, les CVE PHP suivantes affectant les versions 8.2 et 8.4 ne nous concernent pas :

- **CVE-2026-17543** (injection SQL dans ext-pgsql) — le projet utilise Doctrine ORM avec PDO et requêtes préparées, aucun appel aux fonctions natives `pg_insert()`, `pg_update()`, `pg_select()` ou `pg_delete()`. Vérifié par recherche `grep` dans le code source.
- **CVE-2026-9672** (libgd) — pas de traitement d'image côté serveur.
- **CVE-2026-7260** (symlinks Phar) — pas de traitement d'archives .phar depuis des sources externes.

---

## Plan de traitement

### Phase 1 — Mise à jour contrôlée (post-ECF)

```bash
# Sauvegarde du lockfile actuel
cp composer.lock composer.lock.bak-$(date +%Y%m%d)

# Mise à jour des packages vulnérables uniquement
composer update \
    symfony/mailer \
    symfony/http-foundation \
    symfony/runtime \
    symfony/validator \
    symfony/yaml \
    mongodb/mongodb \
    --with-dependencies \
    --no-dev

# Vidage du cache Symfony
php bin/console cache:clear --env=prod --no-debug

# Vérification post-mise à jour
composer audit
```

### Phase 2 — Validation fonctionnelle

Tests de non-régression sur les parcours critiques :

- Connexion / inscription utilisateur
- Affichage du catalogue de menus
- Création de commande
- Envoi d'e-mails transactionnels (confirmation de commande)
- Tableau de bord administrateur

### Phase 3 — Déploiement en production

Suivi de la procédure documentée dans `documentation-deploiement.pdf` (chapitre 6 — Workflow Git et mise en production).

### Phase 4 — Automatisation de l'audit (évolution)

Mise en place prévue :

- **Workflow GitHub Actions** exécutant `composer audit` sur chaque push et pull request
- **Dependabot** activé sur le dépôt GitHub pour détection et PR automatiques
- **Audit mensuel récurrent** manuel avec archivage du rapport dans `docs/`

---

## Conclusion

L'audit révèle un niveau d'exposition **acceptable** pour une application de démonstration ECF. La stack applicative (Doctrine ORM, SMTP, pas d'input YAML utilisateur, MongoDB inactif) limite naturellement l'exploitabilité des vulnérabilités identifiées.

La démarche de maintenance planifiée s'inscrit dans une logique professionnelle de **sécurité continue** plutôt que de patch dans l'urgence, et sera formalisée en workflow automatisé en phase post-ECF.

---

*Document rédigé le 21 octobre 2026 — Laurent MARC — Projet ECF DWWM Studi*
