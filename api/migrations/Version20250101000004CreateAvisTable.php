<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration 004 — Domaine Avis.
 *
 * Table `avis` avec :
 *  - Relation vers utilisateur (auteur, relation *publie*)
 *  - Relation vers commande (une commande = un seul avis, contrainte UNIQUE)
 *  - Relation vers utilisateur (modérateur, nullable)
 *  - Contraintes CHECK sur note (1-5) et statut
 */
final class Version20250101000004CreateAvisTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Domaine Avis : table avis avec modération.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE avis (
                avis_id         SERIAL PRIMARY KEY,
                utilisateur_id  INTEGER NOT NULL,
                commande_id     INTEGER NOT NULL,
                note            SMALLINT NOT NULL,
                commentaire     TEXT NOT NULL,
                statut          VARCHAR(15) NOT NULL DEFAULT 'en_attente',
                date_creation   TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                date_moderation TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                moderateur_id   INTEGER DEFAULT NULL,

                CONSTRAINT fk_avis_utilisateur
                    FOREIGN KEY (utilisateur_id)
                    REFERENCES utilisateur(utilisateur_id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_avis_commande
                    FOREIGN KEY (commande_id)
                    REFERENCES commande(commande_id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_avis_moderateur
                    FOREIGN KEY (moderateur_id)
                    REFERENCES utilisateur(utilisateur_id)
                    ON DELETE SET NULL,

                CONSTRAINT check_note_valide
                    CHECK (note BETWEEN 1 AND 5),

                CONSTRAINT check_statut_avis_valide
                    CHECK (statut IN ('en_attente', 'valide', 'refuse')),

                CONSTRAINT unique_avis_commande
                    UNIQUE (commande_id)
            )
        SQL);

        // Index pour lectures fréquentes
        $this->addSql('CREATE INDEX idx_avis_statut       ON avis(statut)');
        $this->addSql('CREATE INDEX idx_avis_utilisateur  ON avis(utilisateur_id)');
        $this->addSql('CREATE INDEX idx_avis_date         ON avis(date_creation DESC)');
        // Index composite : requête public (statut + note desc pour tri qualité/récence)
        $this->addSql('CREATE INDEX idx_avis_public       ON avis(statut, note DESC, date_creation DESC)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS avis');
    }
}
