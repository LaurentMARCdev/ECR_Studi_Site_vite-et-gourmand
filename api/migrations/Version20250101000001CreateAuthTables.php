<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration 001 — Création des tables pour le domaine Authentification.
 *
 * Contient :
 *  - table `role`         (référentiel des rôles)
 *  - table `utilisateur`  (avec relation vers role)
 *  - index et contraintes de sécurité
 *  - seeding des 3 rôles de base
 */
final class Version20250101000001CreateAuthTables extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Domaine Auth : création des tables role et utilisateur, seeding des rôles.';
    }

    public function up(Schema $schema): void
    {
        // ═══════════════════════════════════════════════════════
        // Table role
        // ═══════════════════════════════════════════════════════
        $this->addSql(<<<'SQL'
            CREATE TABLE role (
                role_id SERIAL PRIMARY KEY,
                libelle VARCHAR(50) NOT NULL UNIQUE
            )
        SQL);

        $this->addSql('CREATE INDEX idx_role_libelle ON role(libelle)');

        // ═══════════════════════════════════════════════════════
        // Table utilisateur
        // ═══════════════════════════════════════════════════════
        $this->addSql(<<<'SQL'
            CREATE TABLE utilisateur (
                utilisateur_id SERIAL PRIMARY KEY,
                email VARCHAR(180) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                nom VARCHAR(100) NOT NULL,
                prenom VARCHAR(100) NOT NULL,
                telephone VARCHAR(30) NOT NULL,
                ville VARCHAR(100) DEFAULT NULL,
                pays VARCHAR(100) DEFAULT NULL,
                adresse_postale VARCHAR(255) NOT NULL,
                role_id INTEGER NOT NULL,
                actif BOOLEAN NOT NULL DEFAULT TRUE,
                date_creation TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                reset_token VARCHAR(255) DEFAULT NULL,
                reset_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                CONSTRAINT fk_utilisateur_role
                    FOREIGN KEY (role_id)
                    REFERENCES role(role_id)
                    ON DELETE RESTRICT
            )
        SQL);

        // Index pour requêtes fréquentes
        $this->addSql('CREATE INDEX idx_utilisateur_email       ON utilisateur(email)');
        $this->addSql('CREATE INDEX idx_utilisateur_role        ON utilisateur(role_id)');
        $this->addSql('CREATE INDEX idx_utilisateur_actif       ON utilisateur(actif)');
        $this->addSql('CREATE INDEX idx_utilisateur_reset_token ON utilisateur(reset_token) WHERE reset_token IS NOT NULL');

        // ═══════════════════════════════════════════════════════
        // Seeding des rôles de base
        // (nécessaire pour que l'inscription fonctionne)
        // ═══════════════════════════════════════════════════════
        $this->addSql("INSERT INTO role (libelle) VALUES ('utilisateur')");
        $this->addSql("INSERT INTO role (libelle) VALUES ('employe')");
        $this->addSql("INSERT INTO role (libelle) VALUES ('administrateur')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS utilisateur');
        $this->addSql('DROP TABLE IF EXISTS role');
    }
}
