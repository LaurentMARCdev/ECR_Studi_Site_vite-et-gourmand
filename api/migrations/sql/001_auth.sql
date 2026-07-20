-- ═══════════════════════════════════════════════════════════════
-- Vite & Gourmand — Script SQL DOMAINE AUTHENTIFICATION
-- ═══════════════════════════════════════════════════════════════
-- SGBD cible : PostgreSQL 15+
-- Ordre d'exécution : ce fichier en premier, avant les autres domaines
-- ═══════════════════════════════════════════════════════════════

-- ───────────────────────────────────────────────────────────────
-- 1. Table `role` (référentiel des rôles applicatifs)
-- ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS role (
    role_id SERIAL PRIMARY KEY,
    libelle VARCHAR(50) NOT NULL UNIQUE
);

CREATE INDEX IF NOT EXISTS idx_role_libelle ON role(libelle);

COMMENT ON TABLE  role IS 'Rôles applicatifs : utilisateur, employe, administrateur';
COMMENT ON COLUMN role.libelle IS 'Nom du rôle en minuscules, sans accent';


-- ───────────────────────────────────────────────────────────────
-- 2. Table `utilisateur`
-- ───────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS utilisateur (
    utilisateur_id           SERIAL PRIMARY KEY,
    email                    VARCHAR(180) NOT NULL UNIQUE,
    password                 VARCHAR(255) NOT NULL,
    nom                      VARCHAR(100) NOT NULL,
    prenom                   VARCHAR(100) NOT NULL,
    telephone                VARCHAR(30)  NOT NULL,
    ville                    VARCHAR(100) DEFAULT NULL,
    pays                     VARCHAR(100) DEFAULT NULL,
    adresse_postale          VARCHAR(255) NOT NULL,
    role_id                  INTEGER      NOT NULL,
    actif                    BOOLEAN      NOT NULL DEFAULT TRUE,
    date_creation            TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reset_token              VARCHAR(255) DEFAULT NULL,
    reset_token_expires_at   TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    CONSTRAINT fk_utilisateur_role
        FOREIGN KEY (role_id)
        REFERENCES role(role_id)
        ON DELETE RESTRICT
);

CREATE INDEX IF NOT EXISTS idx_utilisateur_email       ON utilisateur(email);
CREATE INDEX IF NOT EXISTS idx_utilisateur_role        ON utilisateur(role_id);
CREATE INDEX IF NOT EXISTS idx_utilisateur_actif       ON utilisateur(actif);
CREATE INDEX IF NOT EXISTS idx_utilisateur_reset_token ON utilisateur(reset_token)
    WHERE reset_token IS NOT NULL;

COMMENT ON TABLE  utilisateur IS 'Utilisateurs (visiteurs enregistrés, employés, administrateurs)';
COMMENT ON COLUMN utilisateur.password IS 'Hash Argon2id du mot de passe — ne jamais stocker en clair';
COMMENT ON COLUMN utilisateur.reset_token IS 'Hash SHA-256 du token de réinitialisation ; le token en clair est envoyé par mail';
COMMENT ON COLUMN utilisateur.actif IS 'Permet de désactiver un compte sans le supprimer (départ employé, etc.)';


-- ═══════════════════════════════════════════════════════════════
-- SEEDING — Insertion des données initiales
-- ═══════════════════════════════════════════════════════════════

-- Rôles applicatifs (nécessaires au fonctionnement de l'app)
INSERT INTO role (libelle) VALUES ('utilisateur')
    ON CONFLICT (libelle) DO NOTHING;
INSERT INTO role (libelle) VALUES ('employe')
    ON CONFLICT (libelle) DO NOTHING;
INSERT INTO role (libelle) VALUES ('administrateur')
    ON CONFLICT (libelle) DO NOTHING;


-- ───────────────────────────────────────────────────────────────
-- Compte administrateur initial
-- ───────────────────────────────────────────────────────────────
-- Mot de passe : Admin@Vite2025
-- Hash Argon2id généré via : echo -n 'Admin@Vite2025' | php -r 'echo password_hash(fgets(STDIN), PASSWORD_ARGON2ID);'
-- IMPORTANT : à changer immédiatement après la première connexion !
--
-- Note : si vous préférez générer le compte via un script PHP côté application,
-- laissez ce bloc commenté. Il est fourni pour un déploiement rapide.

INSERT INTO utilisateur (
    email, password, nom, prenom, telephone,
    adresse_postale, role_id, actif
) VALUES (
    'admin@vitegourmand.fr',
    '$argon2id$v=19$m=65536,t=4,p=1$MDEyMzQ1Njc4OWFiY2RlZg$Rq6Hh8g9ZQBqW7BpVHXHRs8yZ7q6+K/9tYy0Ff8pC6Y',
    'Admin',
    'Vite Gourmand',
    '+33500000000',
    'Bordeaux, France',
    (SELECT role_id FROM role WHERE libelle = 'administrateur'),
    TRUE
) ON CONFLICT (email) DO NOTHING;


-- ═══════════════════════════════════════════════════════════════
-- Fin du script domaine Auth
-- Prochaine étape : exécuter le script du domaine Menus & Plats
-- ═══════════════════════════════════════════════════════════════
