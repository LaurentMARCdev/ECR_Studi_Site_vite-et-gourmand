-- ═══════════════════════════════════════════════════════════════
-- Vite & Gourmand — Script SQL DOMAINE COMMANDES
-- ═══════════════════════════════════════════════════════════════
-- SGBD cible : PostgreSQL 15+
-- Ordre d'exécution : après 001_auth.sql et 002_menus.sql
-- ═══════════════════════════════════════════════════════════════

-- ───────────────────────────────────────────────────────────────
-- TABLE COMMANDE (cœur du domaine)
-- ───────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS commande (
    commande_id             SERIAL PRIMARY KEY,
    numero_commande         VARCHAR(20)   NOT NULL UNIQUE,
    utilisateur_id          INTEGER       NOT NULL,
    menu_id                 INTEGER       NOT NULL,

    -- Détails de la prestation
    nombre_personnes        SMALLINT      NOT NULL,
    date_prestation         DATE          NOT NULL,
    heure_livraison         TIME(0)       NOT NULL,
    adresse_livraison       VARCHAR(255)  NOT NULL,
    ville_livraison         VARCHAR(100)  NOT NULL,
    distance_km             DECIMAL(6,2)  NOT NULL DEFAULT 0,

    -- Prix figés au moment de la commande (historique préservé)
    prix_menu               DECIMAL(10,2) NOT NULL,
    reduction               DECIMAL(10,2) NOT NULL DEFAULT 0,
    prix_livraison          DECIMAL(8,2)  NOT NULL,
    prix_total              DECIMAL(10,2) NOT NULL,

    -- Cycle de vie
    statut                  VARCHAR(30)   NOT NULL DEFAULT 'en_attente',
    pret_materiel           BOOLEAN       NOT NULL DEFAULT FALSE,
    restitution_materiel    BOOLEAN       NOT NULL DEFAULT FALSE,
    motif_annulation        TEXT          DEFAULT NULL,
    mode_contact_annulation VARCHAR(10)   DEFAULT NULL,

    -- Traçabilité
    date_commande           TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_modification       TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,

    -- Contraintes référentielles
    CONSTRAINT fk_commande_utilisateur
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(utilisateur_id) ON DELETE RESTRICT,
    CONSTRAINT fk_commande_menu
        FOREIGN KEY (menu_id) REFERENCES menu(menu_id) ON DELETE RESTRICT,

    -- Contraintes métier (défense en profondeur — même si validées côté application)
    CONSTRAINT check_personnes_positif      CHECK (nombre_personnes > 0),
    CONSTRAINT check_prix_menu_positif      CHECK (prix_menu >= 0),
    CONSTRAINT check_prix_total_positif     CHECK (prix_total >= 0),
    CONSTRAINT check_reduction_positive     CHECK (reduction >= 0),
    CONSTRAINT check_prix_livraison_positive CHECK (prix_livraison >= 0),
    CONSTRAINT check_distance_positive      CHECK (distance_km >= 0),

    CONSTRAINT check_statut_valide CHECK (
        statut IN (
            'en_attente', 'accepte', 'en_preparation',
            'en_cours_livraison', 'livre', 'attente_materiel',
            'terminee', 'annulee'
        )
    ),

    CONSTRAINT check_mode_contact_valide CHECK (
        mode_contact_annulation IS NULL
        OR mode_contact_annulation IN ('gsm', 'mail')
    )
);

-- Index pour les requêtes fréquentes
CREATE INDEX IF NOT EXISTS idx_commande_utilisateur     ON commande(utilisateur_id);
CREATE INDEX IF NOT EXISTS idx_commande_menu            ON commande(menu_id);
CREATE INDEX IF NOT EXISTS idx_commande_statut          ON commande(statut);
CREATE INDEX IF NOT EXISTS idx_commande_date_prestation ON commande(date_prestation);
CREATE INDEX IF NOT EXISTS idx_commande_date_commande   ON commande(date_commande DESC);
-- Index composite : filtres employé les plus utilisés
CREATE INDEX IF NOT EXISTS idx_commande_statut_date     ON commande(statut, date_prestation);

COMMENT ON TABLE  commande IS 'Commandes des clients (cœur du domaine transactionnel)';
COMMENT ON COLUMN commande.numero_commande IS 'Identifiant public au format VG-YYYY-NNNN, affiché au client';
COMMENT ON COLUMN commande.prix_menu IS 'Prix du menu figé au moment de la commande (préserve historique si tarif change)';
COMMENT ON COLUMN commande.distance_km IS 'Distance depuis Bordeaux, utilisée pour calculer les frais de livraison';
COMMENT ON COLUMN commande.statut IS 'Cycle de vie : en_attente → accepte → en_preparation → en_cours_livraison → livre → (attente_materiel) → terminee | annulee';


-- ───────────────────────────────────────────────────────────────
-- TABLE HISTORIQUE_STATUT_COMMANDE (traçabilité)
-- ───────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS historique_statut_commande (
    historique_id   SERIAL PRIMARY KEY,
    commande_id     INTEGER      NOT NULL,
    statut          VARCHAR(30)  NOT NULL,
    date_changement TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_historique_commande
        FOREIGN KEY (commande_id)
        REFERENCES commande(commande_id)
        ON DELETE CASCADE,

    CONSTRAINT check_statut_historique_valide CHECK (
        statut IN (
            'en_attente', 'accepte', 'en_preparation',
            'en_cours_livraison', 'livre', 'attente_materiel',
            'terminee', 'annulee'
        )
    )
);

CREATE INDEX IF NOT EXISTS idx_historique_commande
    ON historique_statut_commande(commande_id, date_changement);

COMMENT ON TABLE historique_statut_commande IS 'Timeline des changements de statut d''une commande — utilisé pour l''affichage du suivi côté client';


-- ═══════════════════════════════════════════════════════════════
-- Fin du script domaine Commandes
-- Prochaine étape : script du domaine Avis / Contact / Admin
-- ═══════════════════════════════════════════════════════════════
