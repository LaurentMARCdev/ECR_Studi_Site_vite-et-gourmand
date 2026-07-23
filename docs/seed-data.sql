-- ═══════════════════════════════════════════════════════════════════════
-- SCRIPT D'INITIALISATION DE LA BASE DE DONNÉES VITE & GOURMAND
-- ═══════════════════════════════════════════════════════════════════════
--
-- Projet     : Vite & Gourmand — Traiteur artisanal bordelais
-- Contexte   : ECF DWWM Studi — Laurent MARC
-- Base       : PostgreSQL 15+ (17 en production Alwaysdata)
-- Date       : 23 juillet 2026
-- Version    : 2.0
--
-- OBJECTIF
-- --------
-- Ce script initialise la base de données avec les données minimales
-- nécessaires au fonctionnement du site :
--   1. Référentiels (rôles, thèmes, régimes, allergènes)
--   2. Comptes de démonstration (client, employé, administrateur)
--   3. Catalogue initial de plats
--   4. Catalogue initial de menus avec compositions
--   5. Horaires d'ouverture par défaut
--   6. Un exemple de commande de démonstration
--
-- PRÉREQUIS
-- ---------
-- - La base doit avoir été créée avec les migrations Doctrine préalables
--   (php bin/console doctrine:migrations:migrate --env=prod)
-- - Les tables et contraintes doivent exister
--
-- UTILISATION
-- -----------
--   psql -h postgresql-vitegourmandlm.alwaysdata.net \
--        -U vitegourmandlm_app \
--        -d vitegourmandlm_vg \
--        -f seed-data.sql
--
-- IMPORTANT
-- ---------
-- Les mots de passe des comptes de démonstration sont hashés avec
-- Argon2id (paramètres par défaut Symfony). Ils correspondent aux
-- mots de passe en clair suivants :
--   - Client       : Client@Test2025
--   - Employé      : Employe@Test2025
--   - Administrateur : 'Voir dans le document TP'
--
-- Les hashs ci-dessous doivent être régénérés avec la commande :
--   php bin/console security:hash-password
-- pour garantir leur validité en fonction de l'installation.
--
-- ═══════════════════════════════════════════════════════════════════════

-- Début de la transaction : soit tout passe, soit rien ne passe
BEGIN;

-- ═══════════════════════════════════════════════════════════════════════
-- SECTION 1 : NETTOYAGE (optionnel — à décommenter si réinitialisation)
-- ═══════════════════════════════════════════════════════════════════════
-- ATTENTION : ces commandes suppriment toutes les données existantes.
-- À utiliser uniquement pour une réinitialisation complète.

-- TRUNCATE TABLE commande CASCADE;
-- TRUNCATE TABLE avis CASCADE;
-- TRUNCATE TABLE menu_plat CASCADE;
-- TRUNCATE TABLE menu_theme CASCADE;
-- TRUNCATE TABLE menu_regime CASCADE;
-- TRUNCATE TABLE plat_allergene CASCADE;
-- TRUNCATE TABLE menu CASCADE;
-- TRUNCATE TABLE plat CASCADE;
-- TRUNCATE TABLE utilisateur CASCADE;
-- TRUNCATE TABLE horaire CASCADE;
-- TRUNCATE TABLE contact CASCADE;
-- TRUNCATE TABLE allergene CASCADE;
-- TRUNCATE TABLE regime CASCADE;
-- TRUNCATE TABLE theme CASCADE;
-- TRUNCATE TABLE role CASCADE;


-- ═══════════════════════════════════════════════════════════════════════
-- SECTION 2 : RÉFÉRENTIEL DES RÔLES
-- ═══════════════════════════════════════════════════════════════════════
-- Trois rôles hiérarchiques dans l'application.
-- Un rôle plus élevé hérite des droits des rôles inférieurs.

INSERT INTO role (libelle) VALUES
    ('ROLE_CLIENT'),
    ('ROLE_EMPLOYE'),
    ('ROLE_ADMIN')
ON CONFLICT (libelle) DO NOTHING;


-- ═══════════════════════════════════════════════════════════════════════
-- SECTION 3 : RÉFÉRENTIEL DES THÈMES
-- ═══════════════════════════════════════════════════════════════════════
-- Classification décorative des menus permettant aux clients de filtrer
-- selon l'occasion ou la période de l'année.

INSERT INTO theme (libelle) VALUES
    ('classique'),
    ('evenement'),
    ('noel'),
    ('paques')
ON CONFLICT (libelle) DO NOTHING;


-- ═══════════════════════════════════════════════════════════════════════
-- SECTION 4 : RÉFÉRENTIEL DES RÉGIMES ALIMENTAIRES
-- ═══════════════════════════════════════════════════════════════════════
-- Classification alimentaire des menus. Un menu peut correspondre
-- à plusieurs régimes (par exemple : végétarien ET sans gluten).

INSERT INTO regime (libelle) VALUES
    ('classique'),
    ('vegetarien'),
    ('vegan'),
    ('sans_gluten'),
    ('halal'),
    ('casher')
ON CONFLICT (libelle) DO NOTHING;


-- ═══════════════════════════════════════════════════════════════════════
-- SECTION 5 : RÉFÉRENTIEL DES ALLERGÈNES
-- ═══════════════════════════════════════════════════════════════════════
-- Les 14 allergènes à déclaration obligatoire selon le règlement INCO
-- (Information des Consommateurs) 1169/2011, plus l'alcool en 15ème
-- pour couvrir les préférences religieuses ou personnelles.

INSERT INTO allergene (libelle) VALUES
    ('Gluten'),
    ('Crustaces'),
    ('Oeufs'),
    ('Poisson'),
    ('Arachides'),
    ('Soja'),
    ('Lactose'),
    ('Fruits_a_coque'),
    ('Celeri'),
    ('Moutarde'),
    ('Graines_de_sesame'),
    ('Sulfites'),
    ('Lupin'),
    ('Mollusques'),
    ('Alcool')
ON CONFLICT (libelle) DO NOTHING;


-- ═══════════════════════════════════════════════════════════════════════
-- SECTION 6 : COMPTES DE DÉMONSTRATION
-- ═══════════════════════════════════════════════════════════════════════
-- Trois comptes de démonstration correspondant aux trois profils
-- utilisateurs de l'application.
--
-- Les mots de passe hashés ci-dessous sont des exemples. En production
-- réelle, il est impératif de générer ses propres hashs Argon2id via :
--   php bin/console security:hash-password
--
-- Les hashs Argon2id complets font environ 96 caractères et incluent
-- les paramètres de hashage dans leur préfixe : $argon2id$v=19$m=65536,t=4,p=1$...

-- Client de démonstration : Marie Dupont
INSERT INTO utilisateur (email, password, nom, prenom, telephone, adresse_postale, role_id, actif, date_creation)
SELECT
    'client@vitegourmand.fr',
    '$2y$12$rSa9Q6aIDBCXWoArOD6gr.cMTlUOD9pcOchTq2eEtyhi5NWtZJRt2',
    'Dupont',
    'Marie',
    '0612345678',
    '15 rue Sainte-Catherine, 33000 Bordeaux',
    r.role_id,
    TRUE,
    NOW()
FROM role r
WHERE r.libelle = 'ROLE_CLIENT'
ON CONFLICT (email) DO NOTHING;

-- Employé de démonstration : Julie Martin
INSERT INTO utilisateur (email, password, nom, prenom, telephone, adresse_postale, role_id, actif, date_creation)
SELECT
    'employe@vitegourmand.fr',
    '$2y$12$0pVgmXFPfXkK5y9aflUs/eYUlvQhJyBReZo0u6CghP69XUHnrhiWm',
    'Martin',
    'Julie',
    '0698765432',
    '8 cours Alsace Lorraine, 33000 Bordeaux',
    r.role_id,
    TRUE,
    NOW()
FROM role r
WHERE r.libelle = 'ROLE_EMPLOYE'
ON CONFLICT (email) DO NOTHING;

-- Administrateur de démonstration : José Rodriguez
INSERT INTO utilisateur (email, password, nom, prenom, telephone, adresse_postale, role_id, actif, date_creation)
SELECT
    'admin@vitegourmand.fr',
    '$2y$12$zXc09FlVI9NYUCrAjYejP.zHoRNJj3HYXP39wj1xb0OPPqaKezp1.',
    'Rodriguez',
    'Jose',
    '0654321098',
    '12 place de la Bourse, 33000 Bordeaux',
    r.role_id,
    TRUE,
    NOW()
FROM role r
WHERE r.libelle = 'ROLE_ADMIN'
ON CONFLICT (email) DO NOTHING;


-- ═══════════════════════════════════════════════════════════════════════
-- SECTION 7 : CATALOGUE INITIAL DE PLATS
-- ═══════════════════════════════════════════════════════════════════════
-- Six plats couvrant les trois catégories (deux plats par catégorie).
-- Ces plats servent de base pour composer les menus.

-- ═══ ENTRÉES ═══

INSERT INTO plat (titre, description, categorie, image_url) VALUES
    ('Velouté de potimarron aux graines de courge',
     'Velouté onctueux préparé à partir de potimarrons de saison, relevé d''une pointe de crème fraîche et parsemé de graines de courge torréfiées.',
     'entree',
     '/media/menus/veloute-potimarron.jpg'),

    ('Tartare de saumon frais à l''aneth',
     'Saumon frais du jour finement coupé au couteau, assaisonné d''huile d''olive, aneth ciselé, échalote et zeste de citron. Servi sur toast de pain de seigle.',
     'entree',
     '/media/menus/tartare-saumon.jpg')
ON CONFLICT DO NOTHING;

-- ═══ PLATS PRINCIPAUX ═══

INSERT INTO plat (titre, description, categorie, image_url) VALUES
    ('Filet de boeuf sauce bordelaise',
     'Pièce de boeuf race à viande accompagnée d''une sauce bordelaise à l''échalote et vin rouge, servie avec pommes de terre grenaille rissolées et légumes de saison.',
     'plat_principal',
     '/media/menus/filet-boeuf-bordelaise.jpg'),

    ('Risotto crémeux aux champignons des bois',
     'Risotto Arborio préparé avec un bouillon parfumé, garni de cèpes et girolles poêlés, parmesan affiné 24 mois et huile de truffe blanche.',
     'plat_principal',
     '/media/menus/risotto-champignons.jpg')
ON CONFLICT DO NOTHING;

-- ═══ DESSERTS ═══

INSERT INTO plat (titre, description, categorie, image_url) VALUES
    ('Cannelé bordelais artisanal',
     'Petite pâtisserie emblématique de Bordeaux, à la croûte caramélisée et au coeur moelleux parfumé au rhum et à la vanille. Servi tiède.',
     'dessert',
     '/media/menus/cannele-bordelais.jpg'),

    ('Tiramisu revisité aux fruits rouges',
     'Version fraîche du tiramisu italien avec framboises, myrtilles et fraises, mascarpone allégé et biscuits à la cuillère imbibés de sirop de fruits.',
     'dessert',
     '/media/menus/tiramisu-fruits-rouges.jpg')
ON CONFLICT DO NOTHING;


-- ═══════════════════════════════════════════════════════════════════════
-- SECTION 8 : ASSOCIATION PLATS-ALLERGÈNES
-- ═══════════════════════════════════════════════════════════════════════
-- Déclaration des allergènes présents dans chaque plat conformément
-- au règlement INCO. Cette information est consolidée au niveau menu
-- pour affichage sur les cartes du catalogue.

-- Velouté de potimarron : Lactose (crème fraîche)
INSERT INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, a.allergene_id
FROM plat p, allergene a
WHERE p.titre = 'Velouté de potimarron aux graines de courge'
  AND a.libelle = 'Lactose'
ON CONFLICT DO NOTHING;

-- Tartare de saumon : Poisson, Gluten (toast pain de seigle)
INSERT INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, a.allergene_id
FROM plat p, allergene a
WHERE p.titre = 'Tartare de saumon frais à l''aneth'
  AND a.libelle IN ('Poisson', 'Gluten')
ON CONFLICT DO NOTHING;

-- Filet de boeuf : Sulfites (vin), Celeri (fond de sauce)
INSERT INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, a.allergene_id
FROM plat p, allergene a
WHERE p.titre = 'Filet de boeuf sauce bordelaise'
  AND a.libelle IN ('Sulfites', 'Celeri')
ON CONFLICT DO NOTHING;

-- Risotto champignons : Lactose (parmesan)
INSERT INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, a.allergene_id
FROM plat p, allergene a
WHERE p.titre = 'Risotto crémeux aux champignons des bois'
  AND a.libelle = 'Lactose'
ON CONFLICT DO NOTHING;

-- Cannelé bordelais : Gluten, Oeufs, Lactose, Alcool (rhum)
INSERT INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, a.allergene_id
FROM plat p, allergene a
WHERE p.titre = 'Cannelé bordelais artisanal'
  AND a.libelle IN ('Gluten', 'Oeufs', 'Lactose', 'Alcool')
ON CONFLICT DO NOTHING;

-- Tiramisu : Gluten (biscuits), Oeufs, Lactose (mascarpone)
INSERT INTO plat_allergene (plat_id, allergene_id)
SELECT p.plat_id, a.allergene_id
FROM plat p, allergene a
WHERE p.titre = 'Tiramisu revisité aux fruits rouges'
  AND a.libelle IN ('Gluten', 'Oeufs', 'Lactose')
ON CONFLICT DO NOTHING;


-- ═══════════════════════════════════════════════════════════════════════
-- SECTION 9 : CATALOGUE INITIAL DE MENUS
-- ═══════════════════════════════════════════════════════════════════════
-- Menu de démonstration présenté sur la page d'accueil et le catalogue.

INSERT INTO menu (
    titre,
    description,
    prix_par_personne,
    nombre_personne_minimum,
    quantite_restante,
    conditions,
    actif,
    date_creation
) VALUES (
    'Menu de mariage printanier',
    'Un menu élégant et raffiné pour célébrer les unions printanières. Composé de trois services soigneusement préparés à partir de produits frais et locaux, ce menu allie tradition française et créativité culinaire. Idéal pour les réceptions de mariage, cocktails d''alliance et célébrations familiales.',
    20.00,
    10,
    NULL,
    'Commande à passer au minimum 15 jours avant la prestation. Livraison possible dans Bordeaux Métropole et jusqu''à 30 km au-delà.',
    TRUE,
    NOW()
)
ON CONFLICT DO NOTHING;


-- ═══════════════════════════════════════════════════════════════════════
-- SECTION 10 : ASSOCIATION MENU-THÈMES
-- ═══════════════════════════════════════════════════════════════════════
-- Le menu de mariage printanier est associé aux thèmes "classique"
-- et "evenement" pour apparaître dans les filtres correspondants.

INSERT INTO menu_theme (menu_id, theme_id)
SELECT m.menu_id, t.theme_id
FROM menu m, theme t
WHERE m.titre = 'Menu de mariage printanier'
  AND t.libelle IN ('classique', 'evenement')
ON CONFLICT DO NOTHING;


-- ═══════════════════════════════════════════════════════════════════════
-- SECTION 11 : ASSOCIATION MENU-RÉGIMES
-- ═══════════════════════════════════════════════════════════════════════
-- Le menu est disponible en version classique et végétarienne
-- (la version végétarienne remplace le filet de boeuf par un plat végétal).

INSERT INTO menu_regime (menu_id, regime_id)
SELECT m.menu_id, r.regime_id
FROM menu m, regime r
WHERE m.titre = 'Menu de mariage printanier'
  AND r.libelle IN ('classique', 'vegetarien')
ON CONFLICT DO NOTHING;


-- ═══════════════════════════════════════════════════════════════════════
-- SECTION 12 : ASSOCIATION MENU-PLATS
-- ═══════════════════════════════════════════════════════════════════════
-- Le menu de mariage printanier se compose de trois plats :
-- une entrée, un plat principal et un dessert.

INSERT INTO menu_plat (menu_id, plat_id)
SELECT m.menu_id, p.plat_id
FROM menu m, plat p
WHERE m.titre = 'Menu de mariage printanier'
  AND p.titre IN (
      'Velouté de potimarron aux graines de courge',
      'Filet de boeuf sauce bordelaise',
      'Cannelé bordelais artisanal'
  )
ON CONFLICT DO NOTHING;


-- ═══════════════════════════════════════════════════════════════════════
-- SECTION 13 : HORAIRES D'OUVERTURE PAR DÉFAUT
-- ═══════════════════════════════════════════════════════════════════════
-- Horaires standards d'un traiteur artisanal : ouvert du mardi au samedi,
-- fermé dimanche et lundi (repos hebdomadaire).

INSERT INTO horaire (jour_semaine, ouvert, heure_ouverture, heure_fermeture) VALUES
    ('lundi',    FALSE, NULL,     NULL),
    ('mardi',    TRUE,  '09:00',  '18:00'),
    ('mercredi', TRUE,  '09:00',  '18:00'),
    ('jeudi',    TRUE,  '09:00',  '18:00'),
    ('vendredi', TRUE,  '09:00',  '19:00'),
    ('samedi',   TRUE,  '09:00',  '17:00'),
    ('dimanche', FALSE, NULL,     NULL)
ON CONFLICT (jour_semaine) DO UPDATE
    SET ouvert = EXCLUDED.ouvert,
        heure_ouverture = EXCLUDED.heure_ouverture,
        heure_fermeture = EXCLUDED.heure_fermeture;


-- ═══════════════════════════════════════════════════════════════════════
-- SECTION 14 : COMMANDE DE DÉMONSTRATION
-- ═══════════════════════════════════════════════════════════════════════
-- Une commande fictive passée par Marie Dupont sur le menu de mariage
-- printanier, permettant de démontrer le workflow de commande sans
-- nécessiter de saisie manuelle après déploiement.
--
-- Format du numéro de commande : VG-AAAA-NNNN
--   VG   = préfixe fixe "Vite & Gourmand"
--   AAAA = année sur 4 chiffres
--   NNNN = numéro séquentiel sur 4 chiffres

INSERT INTO commande (
    numero_commande,
    utilisateur_id,
    menu_id,
    nombre_personnes,
    date_prestation,
    heure_livraison,
    adresse_livraison,
    ville_livraison,
    pret_materiel,
    statut,
    prix_menu,
    prix_livraison,
    reduction,
    prix_total,
    date_creation
)
SELECT
    'VG-2026-0001',
    u.utilisateur_id,
    m.menu_id,
    25,
    CURRENT_DATE + INTERVAL '30 days',
    '19:00',
    '15 rue Sainte-Catherine',
    'Bordeaux',
    TRUE,
    'en_attente',
    500.00,
    12.08,
    50.00,
    462.08,
    NOW()
FROM utilisateur u, menu m
WHERE u.email = 'client@vitegourmand.fr'
  AND m.titre = 'Menu de mariage printanier'
ON CONFLICT (numero_commande) DO NOTHING;


-- ═══════════════════════════════════════════════════════════════════════
-- SECTION 15 : VÉRIFICATIONS POST-INSERTION
-- ═══════════════════════════════════════════════════════════════════════
-- Ces requêtes permettent de vérifier que l'insertion s'est correctement
-- déroulée. Décommenter pour exécution.

-- Comptage des enregistrements par table
-- SELECT 'roles' AS table_name, COUNT(*) AS nb FROM role
-- UNION ALL SELECT 'themes', COUNT(*) FROM theme
-- UNION ALL SELECT 'regimes', COUNT(*) FROM regime
-- UNION ALL SELECT 'allergenes', COUNT(*) FROM allergene
-- UNION ALL SELECT 'utilisateurs', COUNT(*) FROM utilisateur
-- UNION ALL SELECT 'plats', COUNT(*) FROM plat
-- UNION ALL SELECT 'menus', COUNT(*) FROM menu
-- UNION ALL SELECT 'horaires', COUNT(*) FROM horaire
-- UNION ALL SELECT 'commandes', COUNT(*) FROM commande;

-- Résultats attendus :
--   roles       : 3
--   themes      : 4
--   regimes     : 6
--   allergenes  : 15
--   utilisateurs: 3
--   plats       : 6
--   menus       : 1
--   horaires    : 7
--   commandes   : 1


-- ═══════════════════════════════════════════════════════════════════════
-- VALIDATION DE LA TRANSACTION
-- ═══════════════════════════════════════════════════════════════════════
-- Si toutes les insertions se sont déroulées sans erreur, on valide.
-- En cas d'erreur, la transaction est annulée automatiquement par
-- ROLLBACK et aucune donnée n'est modifiée.

COMMIT;

-- ═══════════════════════════════════════════════════════════════════════
-- FIN DU SCRIPT
-- ═══════════════════════════════════════════════════════════════════════
