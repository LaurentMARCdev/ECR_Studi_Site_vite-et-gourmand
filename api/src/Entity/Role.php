<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RoleRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Rôles disponibles dans l'application.
 * Correspond à la table `role` du MCD (annexe 1).
 *
 * Rôles attendus : utilisateur, employe, administrateur.
 */
#[ORM\Entity(repositoryClass: RoleRepository::class)]
#[ORM\Table(name: 'role')]
class Role
{
    public const UTILISATEUR     = 'utilisateur';
    public const EMPLOYE         = 'employe';
    public const ADMINISTRATEUR  = 'administrateur';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'role_id', type: 'integer')]
    private ?int $roleId = null;

    #[ORM\Column(name: 'libelle', type: 'string', length: 50, unique: true)]
    private string $libelle;

    public function __construct(string $libelle)
    {
        $this->libelle = $libelle;
    }

    public function getRoleId(): ?int
    {
        return $this->roleId;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): self
    {
        $this->libelle = $libelle;
        return $this;
    }

    /**
     * Convertit le libellé en rôle Symfony (préfixe ROLE_).
     * Exemple : 'utilisateur' → 'ROLE_UTILISATEUR'.
     */
    public function toSymfonyRole(): string
    {
        return 'ROLE_' . strtoupper($this->libelle);
    }
}
