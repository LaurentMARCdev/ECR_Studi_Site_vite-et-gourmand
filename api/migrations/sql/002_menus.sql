-- ═══════════════════════════════════════════════════════════════
-- Vite & Gourmand — Script SQL DOMAINE MENUS & PLATS
-- ═══════════════════════════════════════════════════════════════
-- SGBD cible : PostgreSQL 15+
-- Ordre d'exécution : après 001_auth.sql
-- ═══════════════════════════════════════════════════════════════

-- ───────────────────────────────────────────────────────────────
-- TABLES DE RÉFÉRENCE
-- ───────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS theme (
    theme_id SERIAL PRIMARY KEY,
    libelle  VARCHAR(50) NOT NULL UNIQUE
);
CREATE INDEX IF NOT EXISTS idx_theme_libelle ON theme(libelle);
COMMENT ON TABLE theme IS 'Thèmes des menus : Noël, Pâques, classique, évènement';

CREATE TABLE IF NOT EXISTS regime (
    regime_id SERIAL PRIMARY KEY,
    libelle   VARCHAR(50) NOT NULL UNIQUE
);
CREATE INDEX IF NOT EXISTS idx_regime_libelle ON regime(libelle);
COMMENT ON TABLE regime IS 'Régimes alimentaires : classique, végétarien, vegan, sans gluten, halal, casher…';

CREATE TABLE IF NOT EXISTS allergene (
    allergene_id SERIAL PRIMARY KEY,
    libelle      VARCHAR(50) NOT NULL UNIQUE
);
CREATE INDEX IF NOT EXISTS idx_allergene_libelle ON allergene(libelle);
COMMENT ON TABLE allergene IS '14 allergènes majeurs réglementés (règlement INCO n°1169/2011)';


-- ───────────────────────────────────────────────────────────────
-- TABLE MENU (cœur du domaine)
-- ───────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS menu (
    menu_id                 SERIAL PRIMARY KEY,
    titre                   VARCHAR(150) NOT NULL,
    description             TEXT NOT NULL,
    prix_par_personne       DECIMAL(8,2) NOT NULL,
    nombre_personne_minimum SMALLINT NOT NULL,
    quantite_restante       INTEGER DEFAULT NULL,
    conditions              TEXT DEFAULT NULL,
    actif                   BOOLEAN NOT NULL DEFAULT TRUE,
    date_creation           TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_modification       TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    CONSTRAINT check_prix_positif      CHECK (prix_par_personne > 0),
    CONSTRAINT check_personnes_positif CHECK (nombre_personne_minimum > 0),
    CONSTRAINT check_stock_positif     CHECK (quantite_restante IS NULL OR quantite_restante >= 0)
);
CREATE INDEX IF NOT EXISTS idx_menu_actif        ON menu(actif);
CREATE INDEX IF NOT EXISTS idx_menu_prix         ON menu(prix_par_personne);
CREATE INDEX IF NOT EXISTS idx_menu_date_creation ON menu(date_creation DESC);

COMMENT ON COLUMN menu.quantite_restante IS 'NULL = stock illimité, 0 = épuisé, N = commandes possibles restantes';
COMMENT ON COLUMN menu.actif IS 'Soft delete — préserve l''intégrité des commandes historiques';
COMMENT ON COLUMN menu.conditions IS 'Conditions particulières : délai de commande, précautions de stockage, etc.';


-- ───────────────────────────────────────────────────────────────
-- TABLE PLAT
-- ───────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS plat (
    plat_id     SERIAL PRIMARY KEY,
    titre       VARCHAR(150) NOT NULL,
    description TEXT DEFAULT NULL,
    categorie   VARCHAR(20) NOT NULL,
    image_url   VARCHAR(255) DEFAULT NULL,
    CONSTRAINT check_categorie_valide CHECK (categorie IN ('entree', 'plat_principal', 'dessert'))
);
CREATE INDEX IF NOT EXISTS idx_plat_categorie ON plat(categorie);
CREATE INDEX IF NOT EXISTS idx_plat_titre     ON plat(titre);


-- ───────────────────────────────────────────────────────────────
-- TABLE IMAGE_MENU (galerie)
-- ───────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS image_menu (
    image_id        SERIAL PRIMARY KEY,
    menu_id         INTEGER NOT NULL,
    url             VARCHAR(255) NOT NULL,
    alt_text        VARCHAR(255) DEFAULT NULL,
    ordre_affichage SMALLINT NOT NULL DEFAULT 0,
    est_principale  BOOLEAN NOT NULL DEFAULT FALSE,
    CONSTRAINT fk_image_menu
        FOREIGN KEY (menu_id)
        REFERENCES menu(menu_id)
        ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_image_menu_id ON image_menu(menu_id, ordre_affichage);


-- ───────────────────────────────────────────────────────────────
-- TABLES PIVOT (relations ManyToMany du MCD)
-- ───────────────────────────────────────────────────────────────

-- Relation *propose* : menu ↔ theme
CREATE TABLE IF NOT EXISTS menu_theme (
    menu_id  INTEGER NOT NULL,
    theme_id INTEGER NOT NULL,
    PRIMARY KEY (menu_id, theme_id),
    CONSTRAINT fk_mt_menu  FOREIGN KEY (menu_id)  REFERENCES menu(menu_id)   ON DELETE CASCADE,
    CONSTRAINT fk_mt_theme FOREIGN KEY (theme_id) REFERENCES theme(theme_id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_mt_theme ON menu_theme(theme_id);

-- Relation *adopte* : menu ↔ regime
CREATE TABLE IF NOT EXISTS menu_regime (
    menu_id   INTEGER NOT NULL,
    regime_id INTEGER NOT NULL,
    PRIMARY KEY (menu_id, regime_id),
    CONSTRAINT fk_mr_menu   FOREIGN KEY (menu_id)   REFERENCES menu(menu_id)     ON DELETE CASCADE,
    CONSTRAINT fk_mr_regime FOREIGN KEY (regime_id) REFERENCES regime(regime_id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_mr_regime ON menu_regime(regime_id);

-- Relation *compose* : menu ↔ plat
CREATE TABLE IF NOT EXISTS menu_plat (
    menu_id INTEGER NOT NULL,
    plat_id INTEGER NOT NULL,
    PRIMARY KEY (menu_id, plat_id),
    CONSTRAINT fk_mp_menu FOREIGN KEY (menu_id) REFERENCES menu(menu_id) ON DELETE CASCADE,
    CONSTRAINT fk_mp_plat FOREIGN KEY (plat_id) REFERENCES plat(plat_id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_mp_plat ON menu_plat(plat_id);

-- Relation *contient* : plat ↔ allergene
CREATE TABLE IF NOT EXISTS plat_allergene (
    plat_id      INTEGER NOT NULL,
    allergene_id INTEGER NOT NULL,
    PRIMARY KEY (plat_id, allergene_id),
    CONSTRAINT fk_pa_plat      FOREIGN KEY (plat_id)      REFERENCES plat(plat_id)           ON DELETE CASCADE,
    CONSTRAINT fk_pa_allergene FOREIGN KEY (allergene_id) REFERENCES allergene(allergene_id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_pa_allergene ON plat_allergene(allergene_id);


-- ═══════════════════════════════════════════════════════════════
-- SEEDING DES RÉFÉRENTIELS
-- ═══════════════════════════════════════════════════════════════

-- Thèmes
INSERT INTO theme (libelle) VALUES ('Noël')      ON CONFLICT DO NOTHING;
INSERT INTO theme (libelle) VALUES ('Pâques')    ON CONFLICT DO NOTHING;
INSERT INTO theme (libelle) VALUES ('classique') ON CONFLICT DO NOTHING;
INSERT INTO theme (libelle) VALUES ('évènement') ON CONFLICT DO NOTHING;

-- Régimes (extensible via la table)
INSERT INTO regime (libelle) VALUES ('classique')    ON CONFLICT DO NOTHING;
INSERT INTO regime (libelle) VALUES ('végétarien')   ON CONFLICT DO NOTHING;
INSERT INTO regime (libelle) VALUES ('vegan')        ON CONFLICT DO NOTHING;
INSERT INTO regime (libelle) VALUES ('sans gluten')  ON CONFLICT DO NOTHING;
INSERT INTO regime (libelle) VALUES ('halal')        ON CONFLICT DO NOTHING;
INSERT INTO regime (libelle) VALUES ('casher')       ON CONFLICT DO NOTHING;

-- Allergènes (14 majeurs réglementés + Alcool)
INSERT INTO allergene (libelle) VALUES ('Gluten')            ON CONFLICT DO NOTHING;
INSERT INTO allergene (libelle) VALUES ('Crustacés')         ON CONFLICT DO NOTHING;
INSERT INTO allergene (libelle) VALUES ('Œufs')              ON CONFLICT DO NOTHING;
INSERT INTO allergene (libelle) VALUES ('Poisson')           ON CONFLICT DO NOTHING;
INSERT INTO allergene (libelle) VALUES ('Arachides')         ON CONFLICT DO NOTHING;
INSERT INTO allergene (libelle) VALUES ('Soja')              ON CONFLICT DO NOTHING;
INSERT INTO allergene (libelle) VALUES ('Lactose')           ON CONFLICT DO NOTHING;
INSERT INTO allergene (libelle) VALUES ('Fruits à coque')    ON CONFLICT DO NOTHING;
INSERT INTO allergene (libelle) VALUES ('Céleri')            ON CONFLICT DO NOTHING;
INSERT INTO allergene (libelle) VALUES ('Moutarde')          ON CONFLICT DO NOTHING;
INSERT INTO allergene (libelle) VALUES ('Graines de sésame') ON CONFLICT DO NOTHING;
INSERT INTO allergene (libelle) VALUES ('Sulfites')          ON CONFLICT DO NOTHING;
INSERT INTO allergene (libelle) VALUES ('Lupin')             ON CONFLICT DO NOTHING;
INSERT INTO allergene (libelle) VALUES ('Mollusques')        ON CONFLICT DO NOTHING;
INSERT INTO allergene (libelle) VALUES ('Alcool')            ON CONFLICT DO NOTHING;


-- ═══════════════════════════════════════════════════════════════
-- SEEDING DE DÉMONSTRATION (menus, plats, associations)
-- ═══════════════════════════════════════════════════════════════
-- Ces données permettent de disposer immédiatement d'un environnement
-- de test peuplé. Elles peuvent être supprimées en production.

-- ── PLATS ──────────────────────────────────────────────────────

INSERT INTO plat (titre, description, categorie) VALUES
    ('Foie gras mi-cuit maison',            'Servi avec un chutney de figues et pain brioché toasté.', 'entree'),
    ('Velouté de châtaignes',               'Crème légère, chips de lard fumé et huile de truffe.',    'entree'),
    ('Chapon rôti aux cèpes de Bordeaux',   'Farci à la saucisse et aux herbes, jus de rôti, pommes dauphine.', 'plat_principal'),
    ('Bûche glacée aux marrons',            'Insert crème de marron, meringue italienne, éclats de caramel.',   'dessert'),
    ('Mignardises de Noël',                 'Truffes au chocolat, rochers coco et pâtes de fruits.',   'dessert'),
    ('Œufs cocotte aux truffes',            'Œufs fermiers, crème fraîche, brisures de truffe noire.', 'entree'),
    ('Gigot d''agneau aux herbes',          'Cuisson longue, ail confit, jus corsé, gratin dauphinois.',        'plat_principal'),
    ('Colombe pascale',                     'Brioche italienne aux zestes d''agrumes et amandes.',      'dessert'),
    ('Velouté d''asperges vertes',          'Crème d''asperges, chips d''asperges rôties.',             'entree'),
    ('Risotto printanier aux légumes',      'Riz Arborio, jeunes légumes, parmesan affiné.',           'plat_principal'),
    ('Tarte fine aux fruits rouges',        'Pâte sablée, crème d''amandes, fraises et framboises.',    'dessert'),
    ('Verrine de gaspacho de betterave',    'Gaspacho onctueux, dés de pomme verte, aneth.',           'entree'),
    ('Tajine de légumes aux épices',        'Légumes rôtis, semoule, coriandre, huile d''argan.',       'plat_principal'),
    ('Mousse au chocolat noir 70%',         'Mousse aérienne, éclats de fève de cacao.',               'dessert');

-- Associations plats-allergènes
INSERT INTO plat_allergene (plat_id, allergene_id) VALUES
    ((SELECT plat_id FROM plat WHERE titre='Foie gras mi-cuit maison'),        (SELECT allergene_id FROM allergene WHERE libelle='Gluten')),
    ((SELECT plat_id FROM plat WHERE titre='Foie gras mi-cuit maison'),        (SELECT allergene_id FROM allergene WHERE libelle='Alcool')),
    ((SELECT plat_id FROM plat WHERE titre='Velouté de châtaignes'),           (SELECT allergene_id FROM allergene WHERE libelle='Lactose')),
    ((SELECT plat_id FROM plat WHERE titre='Chapon rôti aux cèpes de Bordeaux'),(SELECT allergene_id FROM allergene WHERE libelle='Gluten')),
    ((SELECT plat_id FROM plat WHERE titre='Chapon rôti aux cèpes de Bordeaux'),(SELECT allergene_id FROM allergene WHERE libelle='Céleri')),
    ((SELECT plat_id FROM plat WHERE titre='Bûche glacée aux marrons'),         (SELECT allergene_id FROM allergene WHERE libelle='Lactose')),
    ((SELECT plat_id FROM plat WHERE titre='Bûche glacée aux marrons'),         (SELECT allergene_id FROM allergene WHERE libelle='Œufs')),
    ((SELECT plat_id FROM plat WHERE titre='Mignardises de Noël'),              (SELECT allergene_id FROM allergene WHERE libelle='Lactose')),
    ((SELECT plat_id FROM plat WHERE titre='Œufs cocotte aux truffes'),         (SELECT allergene_id FROM allergene WHERE libelle='Œufs')),
    ((SELECT plat_id FROM plat WHERE titre='Œufs cocotte aux truffes'),         (SELECT allergene_id FROM allergene WHERE libelle='Lactose')),
    ((SELECT plat_id FROM plat WHERE titre='Colombe pascale'),                  (SELECT allergene_id FROM allergene WHERE libelle='Gluten')),
    ((SELECT plat_id FROM plat WHERE titre='Colombe pascale'),                  (SELECT allergene_id FROM allergene WHERE libelle='Œufs')),
    ((SELECT plat_id FROM plat WHERE titre='Colombe pascale'),                  (SELECT allergene_id FROM allergene WHERE libelle='Fruits à coque')),
    ((SELECT plat_id FROM plat WHERE titre='Risotto printanier aux légumes'),   (SELECT allergene_id FROM allergene WHERE libelle='Lactose')),
    ((SELECT plat_id FROM plat WHERE titre='Tarte fine aux fruits rouges'),     (SELECT allergene_id FROM allergene WHERE libelle='Gluten')),
    ((SELECT plat_id FROM plat WHERE titre='Tarte fine aux fruits rouges'),     (SELECT allergene_id FROM allergene WHERE libelle='Fruits à coque')),
    ((SELECT plat_id FROM plat WHERE titre='Mousse au chocolat noir 70%'),      (SELECT allergene_id FROM allergene WHERE libelle='Œufs')),
    ((SELECT plat_id FROM plat WHERE titre='Mousse au chocolat noir 70%'),      (SELECT allergene_id FROM allergene WHERE libelle='Lactose'));

-- ── MENUS ──────────────────────────────────────────────────────

INSERT INTO menu (titre, description, prix_par_personne, nombre_personne_minimum, quantite_restante, conditions) VALUES
    (
        'Menu de Noël Prestige',
        'Un voyage gustatif pensé pour les fêtes de fin d''année. Foie gras mi-cuit maison, chapon rôti aux cèpes de Bordeaux et bûche glacée aux marrons. Chaque plat est préparé avec des produits locaux sélectionnés par Julie et José.',
        42.00, 8, 5,
        'Ce menu doit être commandé au minimum 7 jours avant la date de la prestation. La livraison s''effectue entre 10h et 14h. Le matériel de service (plats, couverts) est fourni en prêt et doit être restitué sous 3 jours ouvrés.'
    ),
    (
        'Brunch de Pâques',
        'Viennoiseries, œufs cocotte, saumon fumé et desserts printaniers pour un dimanche en famille.',
        28.00, 6, 8,
        'Commande à passer au minimum 5 jours avant la prestation. Livraison entre 9h et 11h.'
    ),
    (
        'Menu Végétarien Printemps',
        'Velouté d''asperges, risotto printanier aux légumes de saison et tarte fine aux fruits rouges. 100% végétarien.',
        24.00, 4, NULL,
        'Menu disponible toute l''année. Commande 48h à l''avance.'
    ),
    (
        'Cocktail Dinatoire Événement',
        '45 pièces par personne : verrines, bruschettas, mini-burgers et mignardises. Idéal pour vos réceptions professionnelles ou familiales.',
        35.00, 20, 3,
        'Commande minimum 10 jours avant la prestation. Prêt de mange-debout et vaisselle inclus, à restituer sous 3 jours.'
    ),
    (
        'Menu Vegan Terre & Saveurs',
        'Gaspacho de betterave, tajine de légumes aux épices et mousse au chocolat noir. Zéro produit animal.',
        22.00, 4, NULL,
        'Menu disponible toute l''année. Commande 48h à l''avance.'
    );

-- Associations menu-thème (relation *propose*)
INSERT INTO menu_theme (menu_id, theme_id) VALUES
    ((SELECT menu_id FROM menu WHERE titre='Menu de Noël Prestige'),         (SELECT theme_id FROM theme WHERE libelle='Noël')),
    ((SELECT menu_id FROM menu WHERE titre='Brunch de Pâques'),              (SELECT theme_id FROM theme WHERE libelle='Pâques')),
    ((SELECT menu_id FROM menu WHERE titre='Menu Végétarien Printemps'),     (SELECT theme_id FROM theme WHERE libelle='classique')),
    ((SELECT menu_id FROM menu WHERE titre='Cocktail Dinatoire Événement'),  (SELECT theme_id FROM theme WHERE libelle='évènement')),
    ((SELECT menu_id FROM menu WHERE titre='Menu Vegan Terre & Saveurs'),    (SELECT theme_id FROM theme WHERE libelle='classique'));

-- Associations menu-régime (relation *adopte*)
INSERT INTO menu_regime (menu_id, regime_id) VALUES
    ((SELECT menu_id FROM menu WHERE titre='Menu de Noël Prestige'),         (SELECT regime_id FROM regime WHERE libelle='classique')),
    ((SELECT menu_id FROM menu WHERE titre='Brunch de Pâques'),              (SELECT regime_id FROM regime WHERE libelle='classique')),
    ((SELECT menu_id FROM menu WHERE titre='Menu Végétarien Printemps'),     (SELECT regime_id FROM regime WHERE libelle='végétarien')),
    ((SELECT menu_id FROM menu WHERE titre='Cocktail Dinatoire Événement'),  (SELECT regime_id FROM regime WHERE libelle='classique')),
    ((SELECT menu_id FROM menu WHERE titre='Menu Vegan Terre & Saveurs'),    (SELECT regime_id FROM regime WHERE libelle='vegan')),
    ((SELECT menu_id FROM menu WHERE titre='Menu Vegan Terre & Saveurs'),    (SELECT regime_id FROM regime WHERE libelle='végétarien'));

-- Compositions des menus (relation *compose*)
-- Menu de Noël Prestige
INSERT INTO menu_plat (menu_id, plat_id) VALUES
    ((SELECT menu_id FROM menu WHERE titre='Menu de Noël Prestige'), (SELECT plat_id FROM plat WHERE titre='Foie gras mi-cuit maison')),
    ((SELECT menu_id FROM menu WHERE titre='Menu de Noël Prestige'), (SELECT plat_id FROM plat WHERE titre='Velouté de châtaignes')),
    ((SELECT menu_id FROM menu WHERE titre='Menu de Noël Prestige'), (SELECT plat_id FROM plat WHERE titre='Chapon rôti aux cèpes de Bordeaux')),
    ((SELECT menu_id FROM menu WHERE titre='Menu de Noël Prestige'), (SELECT plat_id FROM plat WHERE titre='Bûche glacée aux marrons')),
    ((SELECT menu_id FROM menu WHERE titre='Menu de Noël Prestige'), (SELECT plat_id FROM plat WHERE titre='Mignardises de Noël'));

-- Brunch de Pâques
INSERT INTO menu_plat (menu_id, plat_id) VALUES
    ((SELECT menu_id FROM menu WHERE titre='Brunch de Pâques'), (SELECT plat_id FROM plat WHERE titre='Œufs cocotte aux truffes')),
    ((SELECT menu_id FROM menu WHERE titre='Brunch de Pâques'), (SELECT plat_id FROM plat WHERE titre='Gigot d''agneau aux herbes')),
    ((SELECT menu_id FROM menu WHERE titre='Brunch de Pâques'), (SELECT plat_id FROM plat WHERE titre='Colombe pascale'));

-- Menu Végétarien Printemps
INSERT INTO menu_plat (menu_id, plat_id) VALUES
    ((SELECT menu_id FROM menu WHERE titre='Menu Végétarien Printemps'), (SELECT plat_id FROM plat WHERE titre='Velouté d''asperges vertes')),
    ((SELECT menu_id FROM menu WHERE titre='Menu Végétarien Printemps'), (SELECT plat_id FROM plat WHERE titre='Risotto printanier aux légumes')),
    ((SELECT menu_id FROM menu WHERE titre='Menu Végétarien Printemps'), (SELECT plat_id FROM plat WHERE titre='Tarte fine aux fruits rouges'));

-- Menu Vegan Terre & Saveurs
INSERT INTO menu_plat (menu_id, plat_id) VALUES
    ((SELECT menu_id FROM menu WHERE titre='Menu Vegan Terre & Saveurs'), (SELECT plat_id FROM plat WHERE titre='Verrine de gaspacho de betterave')),
    ((SELECT menu_id FROM menu WHERE titre='Menu Vegan Terre & Saveurs'), (SELECT plat_id FROM plat WHERE titre='Tajine de légumes aux épices')),
    ((SELECT menu_id FROM menu WHERE titre='Menu Vegan Terre & Saveurs'), (SELECT plat_id FROM plat WHERE titre='Mousse au chocolat noir 70%'));


-- ═══════════════════════════════════════════════════════════════
-- Fin du script domaine Menus & Plats
-- Prochaine étape : script du domaine Commandes
-- ═══════════════════════════════════════════════════════════════
