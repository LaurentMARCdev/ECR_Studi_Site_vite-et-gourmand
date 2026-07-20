-- ═══════════════════════════════════════════════════════════════
-- Vite & Gourmand — Script SQL DOMAINE HORAIRES
-- ═══════════════════════════════════════════════════════════════
-- SGBD cible : PostgreSQL 15+
-- Ordre d'exécution : après les autres scripts (indépendant)
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS horaire (
    horaire_id       SERIAL PRIMARY KEY,
    jour             VARCHAR(15) NOT NULL UNIQUE,
    ordre_jour       SMALLINT    NOT NULL UNIQUE,   -- 1 (lundi) → 7 (dimanche)
    heure_ouverture  TIME(0)     DEFAULT NULL,      -- NULL si fermé
    heure_fermeture  TIME(0)     DEFAULT NULL,      -- NULL si fermé
    ferme            BOOLEAN     NOT NULL DEFAULT FALSE,

    CONSTRAINT check_jour_valide CHECK (
        jour IN ('Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche')
    ),

    CONSTRAINT check_ordre_valide CHECK (ordre_jour BETWEEN 1 AND 7),

    -- Contrainte de cohérence : un jour ouvert doit avoir des heures cohérentes
    CONSTRAINT check_heures_coherentes CHECK (
        ferme = TRUE
        OR (heure_ouverture IS NOT NULL
            AND heure_fermeture IS NOT NULL
            AND heure_ouverture < heure_fermeture)
    )
);

CREATE INDEX IF NOT EXISTS idx_horaire_ordre ON horaire(ordre_jour);

COMMENT ON TABLE  horaire IS 'Horaires d''ouverture — 7 lignes fixes (une par jour)';
COMMENT ON COLUMN horaire.ordre_jour IS 'Ordre 1 (lundi) à 7 (dimanche) pour tri prévisible';
COMMENT ON COLUMN horaire.ferme IS 'TRUE si le jour est fermé (les heures sont alors NULL)';


-- ═══════════════════════════════════════════════════════════════
-- SEEDING des 7 jours (valeurs par défaut)
-- ═══════════════════════════════════════════════════════════════

INSERT INTO horaire (jour, ordre_jour, heure_ouverture, heure_fermeture, ferme) VALUES
    ('Lundi',    1, '08:00', '19:00', FALSE),
    ('Mardi',    2, '08:00', '19:00', FALSE),
    ('Mercredi', 3, '08:00', '19:00', FALSE),
    ('Jeudi',    4, '08:00', '19:00', FALSE),
    ('Vendredi', 5, '08:00', '20:00', FALSE),
    ('Samedi',   6, '09:00', '18:00', FALSE),
    ('Dimanche', 7, NULL,    NULL,    TRUE)
ON CONFLICT (jour) DO NOTHING;


-- ═══════════════════════════════════════════════════════════════
-- Fin du script domaine Horaires
-- ═══════════════════════════════════════════════════════════════
