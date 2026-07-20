<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration 005 — Domaine Horaires.
 *
 * Table `horaire` avec seeding des 7 jours de la semaine.
 * Les horaires par défaut correspondent à ce que le front affiche
 * en mode démo (Lun-Ven 8h-19h, Sam 9h-18h, Dim fermé).
 */
final class Version20250101000005CreateHoraireTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Domaine Horaires : table horaire + seeding des 7 jours.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE horaire (
                horaire_id       SERIAL PRIMARY KEY,
                jour             VARCHAR(15) NOT NULL UNIQUE,
                ordre_jour       SMALLINT NOT NULL UNIQUE,
                heure_ouverture  TIME(0) DEFAULT NULL,
                heure_fermeture  TIME(0) DEFAULT NULL,
                ferme            BOOLEAN NOT NULL DEFAULT FALSE,

                CONSTRAINT check_jour_valide
                    CHECK (jour IN ('Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche')),

                CONSTRAINT check_ordre_valide
                    CHECK (ordre_jour BETWEEN 1 AND 7),

                CONSTRAINT check_heures_coherentes
                    CHECK (
                        ferme = TRUE
                        OR (heure_ouverture IS NOT NULL
                            AND heure_fermeture IS NOT NULL
                            AND heure_ouverture < heure_fermeture)
                    )
            )
        SQL);

        $this->addSql('CREATE INDEX idx_horaire_ordre ON horaire(ordre_jour)');

        // Seeding des 7 jours
        $this->addSql("INSERT INTO horaire (jour, ordre_jour, heure_ouverture, heure_fermeture, ferme) VALUES ('Lundi',    1, '08:00', '19:00', FALSE)");
        $this->addSql("INSERT INTO horaire (jour, ordre_jour, heure_ouverture, heure_fermeture, ferme) VALUES ('Mardi',    2, '08:00', '19:00', FALSE)");
        $this->addSql("INSERT INTO horaire (jour, ordre_jour, heure_ouverture, heure_fermeture, ferme) VALUES ('Mercredi', 3, '08:00', '19:00', FALSE)");
        $this->addSql("INSERT INTO horaire (jour, ordre_jour, heure_ouverture, heure_fermeture, ferme) VALUES ('Jeudi',    4, '08:00', '19:00', FALSE)");
        $this->addSql("INSERT INTO horaire (jour, ordre_jour, heure_ouverture, heure_fermeture, ferme) VALUES ('Vendredi', 5, '08:00', '20:00', FALSE)");
        $this->addSql("INSERT INTO horaire (jour, ordre_jour, heure_ouverture, heure_fermeture, ferme) VALUES ('Samedi',   6, '09:00', '18:00', FALSE)");
        $this->addSql("INSERT INTO horaire (jour, ordre_jour, heure_ouverture, heure_fermeture, ferme) VALUES ('Dimanche', 7, NULL,    NULL,    TRUE)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS horaire');
    }
}
