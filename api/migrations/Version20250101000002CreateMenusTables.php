<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration 002 — Domaine Menus & Plats.
 *
 * Contient :
 *  - Tables référentielles : theme, regime, allergene
 *  - Tables principales : menu, plat, image_menu
 *  - Tables pivot : menu_theme, menu_regime, menu_plat, plat_allergene
 *  - Seeding des référentiels
 */
final class Version20250101000002CreateMenusTables extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Domaine Menus & Plats : tables + référentiels + seeding.';
    }

    public function up(Schema $schema): void
    {
        // ═══════════════════════════════════════════════════════
        // Tables référentielles
        // ═══════════════════════════════════════════════════════

        $this->addSql(<<<'SQL'
            CREATE TABLE theme (
                theme_id SERIAL PRIMARY KEY,
                libelle VARCHAR(50) NOT NULL UNIQUE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_theme_libelle ON theme(libelle)');

        $this->addSql(<<<'SQL'
            CREATE TABLE regime (
                regime_id SERIAL PRIMARY KEY,
                libelle VARCHAR(50) NOT NULL UNIQUE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_regime_libelle ON regime(libelle)');

        $this->addSql(<<<'SQL'
            CREATE TABLE allergene (
                allergene_id SERIAL PRIMARY KEY,
                libelle VARCHAR(50) NOT NULL UNIQUE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_allergene_libelle ON allergene(libelle)');

        // ═══════════════════════════════════════════════════════
        // Table menu (cœur du domaine)
        // ═══════════════════════════════════════════════════════

        $this->addSql(<<<'SQL'
            CREATE TABLE menu (
                menu_id SERIAL PRIMARY KEY,
                titre VARCHAR(150) NOT NULL,
                description TEXT NOT NULL,
                prix_par_personne DECIMAL(8,2) NOT NULL,
                nombre_personne_minimum SMALLINT NOT NULL,
                quantite_restante INTEGER DEFAULT NULL,
                conditions TEXT DEFAULT NULL,
                actif BOOLEAN NOT NULL DEFAULT TRUE,
                date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                date_modification TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                CONSTRAINT check_prix_positif CHECK (prix_par_personne > 0),
                CONSTRAINT check_personnes_positif CHECK (nombre_personne_minimum > 0),
                CONSTRAINT check_stock_positif CHECK (quantite_restante IS NULL OR quantite_restante >= 0)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_menu_actif        ON menu(actif)');
        $this->addSql('CREATE INDEX idx_menu_prix         ON menu(prix_par_personne)');
        $this->addSql('CREATE INDEX idx_menu_date_creation ON menu(date_creation DESC)');

        // ═══════════════════════════════════════════════════════
        // Table plat
        // ═══════════════════════════════════════════════════════

        $this->addSql(<<<'SQL'
            CREATE TABLE plat (
                plat_id SERIAL PRIMARY KEY,
                titre VARCHAR(150) NOT NULL,
                description TEXT DEFAULT NULL,
                categorie VARCHAR(20) NOT NULL,
                image_url VARCHAR(255) DEFAULT NULL,
                CONSTRAINT check_categorie_valide CHECK (categorie IN ('entree', 'plat_principal', 'dessert'))
            )
        SQL);
        $this->addSql('CREATE INDEX idx_plat_categorie ON plat(categorie)');
        $this->addSql('CREATE INDEX idx_plat_titre     ON plat(titre)');

        // ═══════════════════════════════════════════════════════
        // Table image_menu (galerie)
        // ═══════════════════════════════════════════════════════

        $this->addSql(<<<'SQL'
            CREATE TABLE image_menu (
                image_id SERIAL PRIMARY KEY,
                menu_id INTEGER NOT NULL,
                url VARCHAR(255) NOT NULL,
                alt_text VARCHAR(255) DEFAULT NULL,
                ordre_affichage SMALLINT NOT NULL DEFAULT 0,
                est_principale BOOLEAN NOT NULL DEFAULT FALSE,
                CONSTRAINT fk_image_menu
                    FOREIGN KEY (menu_id)
                    REFERENCES menu(menu_id)
                    ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_image_menu_id ON image_menu(menu_id, ordre_affichage)');

        // ═══════════════════════════════════════════════════════
        // Tables pivot ManyToMany
        // ═══════════════════════════════════════════════════════

        // menu_theme (relation *propose*)
        $this->addSql(<<<'SQL'
            CREATE TABLE menu_theme (
                menu_id  INTEGER NOT NULL,
                theme_id INTEGER NOT NULL,
                PRIMARY KEY (menu_id, theme_id),
                CONSTRAINT fk_mt_menu  FOREIGN KEY (menu_id)  REFERENCES menu(menu_id)  ON DELETE CASCADE,
                CONSTRAINT fk_mt_theme FOREIGN KEY (theme_id) REFERENCES theme(theme_id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_mt_theme ON menu_theme(theme_id)');

        // menu_regime (relation *adopte*)
        $this->addSql(<<<'SQL'
            CREATE TABLE menu_regime (
                menu_id   INTEGER NOT NULL,
                regime_id INTEGER NOT NULL,
                PRIMARY KEY (menu_id, regime_id),
                CONSTRAINT fk_mr_menu   FOREIGN KEY (menu_id)   REFERENCES menu(menu_id)     ON DELETE CASCADE,
                CONSTRAINT fk_mr_regime FOREIGN KEY (regime_id) REFERENCES regime(regime_id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_mr_regime ON menu_regime(regime_id)');

        // menu_plat (relation *compose*)
        $this->addSql(<<<'SQL'
            CREATE TABLE menu_plat (
                menu_id INTEGER NOT NULL,
                plat_id INTEGER NOT NULL,
                PRIMARY KEY (menu_id, plat_id),
                CONSTRAINT fk_mp_menu FOREIGN KEY (menu_id) REFERENCES menu(menu_id) ON DELETE CASCADE,
                CONSTRAINT fk_mp_plat FOREIGN KEY (plat_id) REFERENCES plat(plat_id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_mp_plat ON menu_plat(plat_id)');

        // plat_allergene (relation *contient*)
        $this->addSql(<<<'SQL'
            CREATE TABLE plat_allergene (
                plat_id      INTEGER NOT NULL,
                allergene_id INTEGER NOT NULL,
                PRIMARY KEY (plat_id, allergene_id),
                CONSTRAINT fk_pa_plat      FOREIGN KEY (plat_id)      REFERENCES plat(plat_id)           ON DELETE CASCADE,
                CONSTRAINT fk_pa_allergene FOREIGN KEY (allergene_id) REFERENCES allergene(allergene_id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_pa_allergene ON plat_allergene(allergene_id)');

        // ═══════════════════════════════════════════════════════
        // Seeding des référentiels
        // ═══════════════════════════════════════════════════════

        // Thèmes
        $this->addSql("INSERT INTO theme (libelle) VALUES ('Noël')");
        $this->addSql("INSERT INTO theme (libelle) VALUES ('Pâques')");
        $this->addSql("INSERT INTO theme (libelle) VALUES ('classique')");
        $this->addSql("INSERT INTO theme (libelle) VALUES ('évènement')");

        // Régimes
        $this->addSql("INSERT INTO regime (libelle) VALUES ('classique')");
        $this->addSql("INSERT INTO regime (libelle) VALUES ('végétarien')");
        $this->addSql("INSERT INTO regime (libelle) VALUES ('vegan')");
        $this->addSql("INSERT INTO regime (libelle) VALUES ('sans gluten')");
        $this->addSql("INSERT INTO regime (libelle) VALUES ('halal')");
        $this->addSql("INSERT INTO regime (libelle) VALUES ('casher')");

        // Allergènes (14 majeurs — règlement INCO)
        $this->addSql("INSERT INTO allergene (libelle) VALUES ('Gluten')");
        $this->addSql("INSERT INTO allergene (libelle) VALUES ('Crustacés')");
        $this->addSql("INSERT INTO allergene (libelle) VALUES ('Œufs')");
        $this->addSql("INSERT INTO allergene (libelle) VALUES ('Poisson')");
        $this->addSql("INSERT INTO allergene (libelle) VALUES ('Arachides')");
        $this->addSql("INSERT INTO allergene (libelle) VALUES ('Soja')");
        $this->addSql("INSERT INTO allergene (libelle) VALUES ('Lactose')");
        $this->addSql("INSERT INTO allergene (libelle) VALUES ('Fruits à coque')");
        $this->addSql("INSERT INTO allergene (libelle) VALUES ('Céleri')");
        $this->addSql("INSERT INTO allergene (libelle) VALUES ('Moutarde')");
        $this->addSql("INSERT INTO allergene (libelle) VALUES ('Graines de sésame')");
        $this->addSql("INSERT INTO allergene (libelle) VALUES ('Sulfites')");
        $this->addSql("INSERT INTO allergene (libelle) VALUES ('Lupin')");
        $this->addSql("INSERT INTO allergene (libelle) VALUES ('Mollusques')");
        $this->addSql("INSERT INTO allergene (libelle) VALUES ('Alcool')");
    }

    public function down(Schema $schema): void
    {
        // Ordre inverse — les tables pivot d'abord
        $this->addSql('DROP TABLE IF EXISTS plat_allergene');
        $this->addSql('DROP TABLE IF EXISTS menu_plat');
        $this->addSql('DROP TABLE IF EXISTS menu_regime');
        $this->addSql('DROP TABLE IF EXISTS menu_theme');
        $this->addSql('DROP TABLE IF EXISTS image_menu');
        $this->addSql('DROP TABLE IF EXISTS plat');
        $this->addSql('DROP TABLE IF EXISTS menu');
        $this->addSql('DROP TABLE IF EXISTS allergene');
        $this->addSql('DROP TABLE IF EXISTS regime');
        $this->addSql('DROP TABLE IF EXISTS theme');
    }
}
