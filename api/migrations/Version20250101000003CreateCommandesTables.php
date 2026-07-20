<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration 003 — Domaine Commandes.
 *
 * Contient :
 *  - Table `commande` (cœur du domaine)
 *  - Table `historique_statut_commande` (traçabilité du cycle de vie)
 *  - Contraintes CHECK sur les statuts et prix
 *  - Index pour requêtes fréquentes (par utilisateur, par statut, par date)
 */
final class Version20250101000003CreateCommandesTables extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Domaine Commandes : tables commande + historique_statut_commande.';
    }

    public function up(Schema $schema): void
    {
        // ═══════════════════════════════════════════════════════
        // Table commande
        // ═══════════════════════════════════════════════════════
        $this->addSql(<<<'SQL'
            CREATE TABLE commande (
                commande_id             SERIAL PRIMARY KEY,
                numero_commande         VARCHAR(20)  NOT NULL UNIQUE,
                utilisateur_id          INTEGER      NOT NULL,
                menu_id                 INTEGER      NOT NULL,
                nombre_personnes        SMALLINT     NOT NULL,
                date_prestation         DATE         NOT NULL,
                heure_livraison         TIME(0)      NOT NULL,
                adresse_livraison       VARCHAR(255) NOT NULL,
                ville_livraison         VARCHAR(100) NOT NULL,
                distance_km             DECIMAL(6,2) NOT NULL DEFAULT 0,
                prix_menu               DECIMAL(10,2) NOT NULL,
                reduction               DECIMAL(10,2) NOT NULL DEFAULT 0,
                prix_livraison          DECIMAL(8,2)  NOT NULL,
                prix_total              DECIMAL(10,2) NOT NULL,
                statut                  VARCHAR(30)   NOT NULL DEFAULT 'en_attente',
                pret_materiel           BOOLEAN       NOT NULL DEFAULT FALSE,
                restitution_materiel    BOOLEAN       NOT NULL DEFAULT FALSE,
                motif_annulation        TEXT          DEFAULT NULL,
                mode_contact_annulation VARCHAR(10)   DEFAULT NULL,
                date_commande           TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                date_modification       TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,

                CONSTRAINT fk_commande_utilisateur
                    FOREIGN KEY (utilisateur_id)
                    REFERENCES utilisateur(utilisateur_id)
                    ON DELETE RESTRICT,

                CONSTRAINT fk_commande_menu
                    FOREIGN KEY (menu_id)
                    REFERENCES menu(menu_id)
                    ON DELETE RESTRICT,

                CONSTRAINT check_personnes_positif
                    CHECK (nombre_personnes > 0),

                CONSTRAINT check_prix_menu_positif
                    CHECK (prix_menu >= 0),

                CONSTRAINT check_prix_total_positif
                    CHECK (prix_total >= 0),

                CONSTRAINT check_reduction_positive
                    CHECK (reduction >= 0),

                CONSTRAINT check_prix_livraison_positive
                    CHECK (prix_livraison >= 0),

                CONSTRAINT check_distance_positive
                    CHECK (distance_km >= 0),

                CONSTRAINT check_statut_valide
                    CHECK (statut IN (
                        'en_attente', 'accepte', 'en_preparation',
                        'en_cours_livraison', 'livre', 'attente_materiel',
                        'terminee', 'annulee'
                    )),

                CONSTRAINT check_mode_contact_valide
                    CHECK (mode_contact_annulation IS NULL
                        OR mode_contact_annulation IN ('gsm', 'mail'))
            )
        SQL);

        // Index pour requêtes fréquentes
        $this->addSql('CREATE INDEX idx_commande_utilisateur     ON commande(utilisateur_id)');
        $this->addSql('CREATE INDEX idx_commande_menu            ON commande(menu_id)');
        $this->addSql('CREATE INDEX idx_commande_statut          ON commande(statut)');
        $this->addSql('CREATE INDEX idx_commande_date_prestation ON commande(date_prestation)');
        $this->addSql('CREATE INDEX idx_commande_date_commande   ON commande(date_commande DESC)');
        // Index composite pour les filtres employé fréquents (statut + date)
        $this->addSql('CREATE INDEX idx_commande_statut_date     ON commande(statut, date_prestation)');

        // ═══════════════════════════════════════════════════════
        // Table historique_statut_commande
        // ═══════════════════════════════════════════════════════
        $this->addSql(<<<'SQL'
            CREATE TABLE historique_statut_commande (
                historique_id   SERIAL PRIMARY KEY,
                commande_id     INTEGER NOT NULL,
                statut          VARCHAR(30) NOT NULL,
                date_changement TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT fk_historique_commande
                    FOREIGN KEY (commande_id)
                    REFERENCES commande(commande_id)
                    ON DELETE CASCADE,

                CONSTRAINT check_statut_historique_valide
                    CHECK (statut IN (
                        'en_attente', 'accepte', 'en_preparation',
                        'en_cours_livraison', 'livre', 'attente_materiel',
                        'terminee', 'annulee'
                    ))
            )
        SQL);

        $this->addSql('CREATE INDEX idx_historique_commande ON historique_statut_commande(commande_id, date_changement)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS historique_statut_commande');
        $this->addSql('DROP TABLE IF EXISTS commande');
    }
}
