-- ═══════════════════════════════════════════════════════════════
-- Vite & Gourmand — Script SQL DOMAINE AVIS
-- ═══════════════════════════════════════════════════════════════
-- SGBD cible : PostgreSQL 15+
-- Ordre d'exécution : après 001_auth.sql, 002_menus.sql, 003_commandes.sql
-- ═══════════════════════════════════════════════════════════════

-- ───────────────────────────────────────────────────────────────
-- TABLE AVIS
-- ───────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS avis (
    avis_id         SERIAL PRIMARY KEY,
    utilisateur_id  INTEGER      NOT NULL,     -- auteur (relation *publie* du MCD)
    commande_id     INTEGER      NOT NULL,     -- commande concernée
    note            SMALLINT     NOT NULL,     -- 1 à 5 étoiles
    commentaire     TEXT         NOT NULL,
    statut          VARCHAR(15)  NOT NULL DEFAULT 'en_attente',
    date_creation   TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    date_moderation TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    moderateur_id   INTEGER      DEFAULT NULL, -- employé/admin qui a modéré

    -- Contraintes référentielles
    CONSTRAINT fk_avis_utilisateur
        FOREIGN KEY (utilisateur_id) REFERENCES utilisateur(utilisateur_id) ON DELETE CASCADE,
    CONSTRAINT fk_avis_commande
        FOREIGN KEY (commande_id)    REFERENCES commande(commande_id)      ON DELETE CASCADE,
    CONSTRAINT fk_avis_moderateur
        FOREIGN KEY (moderateur_id)  REFERENCES utilisateur(utilisateur_id) ON DELETE SET NULL,

    -- Contraintes métier
    CONSTRAINT check_note_valide         CHECK (note BETWEEN 1 AND 5),
    CONSTRAINT check_statut_avis_valide  CHECK (statut IN ('en_attente', 'valide', 'refuse')),

    -- Un utilisateur ne peut déposer qu'un seul avis par commande
    CONSTRAINT unique_avis_commande      UNIQUE (commande_id)
);

CREATE INDEX IF NOT EXISTS idx_avis_statut      ON avis(statut);
CREATE INDEX IF NOT EXISTS idx_avis_utilisateur ON avis(utilisateur_id);
CREATE INDEX IF NOT EXISTS idx_avis_date        ON avis(date_creation DESC);
-- Index composite pour la requête publique (page d'accueil) :
--   statut='valide' + tri par note desc + date desc
CREATE INDEX IF NOT EXISTS idx_avis_public
    ON avis(statut, note DESC, date_creation DESC);

COMMENT ON TABLE  avis IS 'Avis clients — publiés sur l''accueil après modération employé/admin';
COMMENT ON COLUMN avis.note IS 'Note de 1 à 5 étoiles';
COMMENT ON COLUMN avis.statut IS 'Modération : en_attente → valide (public) | refuse (non affiché)';
COMMENT ON COLUMN avis.moderateur_id IS 'Employé/admin ayant validé ou refusé — traçabilité';
COMMENT ON CONSTRAINT unique_avis_commande ON avis IS 'Un seul avis par commande (règle métier)';


-- ═══════════════════════════════════════════════════════════════
-- Fin du script domaine Avis
-- Il ne reste plus que : Contact (POST simple) et Horaires (CRUD léger)
-- ═══════════════════════════════════════════════════════════════
