<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité Utilisateur — correspond à la table `utilisateur` du MCD (annexe 1).
 *
 * Implémente les interfaces Symfony Security pour permettre l'authentification.
 * Le hachage du mot de passe est géré par le PasswordHasher configuré dans
 * security.yaml (Argon2id par défaut).
 *
 * Sécurité :
 *  - password : jamais retourné en JSON (getter non-exposé côté API)
 *  - resetToken : token d'usage unique pour la réinitialisation de mdp
 *  - actif : permet à l'admin de désactiver un compte sans le supprimer
 */
#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'utilisateur')]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse e-mail est déjà utilisée.')]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'utilisateur_id', type: 'integer')]
    private ?int $utilisateurId = null;

    #[ORM\Column(name: 'email', type: 'string', length: 180, unique: true)]
    #[Assert\NotBlank(message: 'L\'e-mail est obligatoire.')]
    #[Assert\Email(message: 'Format d\'e-mail invalide.')]
    #[Assert\Length(max: 180)]
    private string $email;

    /**
     * Mot de passe haché (Argon2id).
     * Ne JAMAIS exposer en JSON.
     */
    #[ORM\Column(name: 'password', type: 'string', length: 255)]
    private string $password;

    #[ORM\Column(name: 'nom', type: 'string', length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 100)]
    private string $nom;

    #[ORM\Column(name: 'prenom', type: 'string', length: 100)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(max: 100)]
    private string $prenom;

    #[ORM\Column(name: 'telephone', type: 'string', length: 30)]
    #[Assert\NotBlank(message: 'Le numéro de téléphone est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^(\+?\d[\d\s\-().]{6,18}\d)$/',
        message: 'Format de numéro invalide.'
    )]
    private string $telephone;

    #[ORM\Column(name: 'ville', type: 'string', length: 100, nullable: true)]
    private ?string $ville = null;

    #[ORM\Column(name: 'pays', type: 'string', length: 100, nullable: true)]
    private ?string $pays = null;

    #[ORM\Column(name: 'adresse_postale', type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'L\'adresse postale est obligatoire.')]
    #[Assert\Length(max: 255)]
    private string $adressePostale;

    /**
     * Relation "possede" du MCD :
     *   utilisateur (0,N) --- possede --- (1,1) role
     * On stocke le rôle via une relation ManyToOne.
     */
    #[ORM\ManyToOne(targetEntity: Role::class)]
    #[ORM\JoinColumn(name: 'role_id', referencedColumnName: 'role_id', nullable: false)]
    private Role $role;

    /**
     * Permet à l'administrateur de désactiver un compte
     * (ex: départ d'un employé) sans supprimer les données historiques.
     */
    #[ORM\Column(name: 'actif', type: 'boolean', options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(name: 'date_creation', type: 'datetime_immutable')]
    private \DateTimeImmutable $dateCreation;

    /**
     * Token temporaire pour la réinitialisation du mot de passe.
     * Généré via random_bytes(32), stocké haché, expire après 1 h.
     */
    #[ORM\Column(name: 'reset_token', type: 'string', length: 255, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(name: 'reset_token_expires_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $resetTokenExpiresAt = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getUtilisateurId(): ?int
    {
        return $this->utilisateurId;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = strtolower(trim($email));
        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = trim($nom);
        return $this;
    }

    public function getPrenom(): string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): self
    {
        $this->prenom = trim($prenom);
        return $this;
    }

    public function getTelephone(): string
    {
        return $this->telephone;
    }

    public function setTelephone(string $telephone): self
    {
        $this->telephone = trim($telephone);
        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): self
    {
        $this->ville = $ville;
        return $this;
    }

    public function getPays(): ?string
    {
        return $this->pays;
    }

    public function setPays(?string $pays): self
    {
        $this->pays = $pays;
        return $this;
    }

    public function getAdressePostale(): string
    {
        return $this->adressePostale;
    }

    public function setAdressePostale(string $adressePostale): self
    {
        $this->adressePostale = trim($adressePostale);
        return $this;
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function setRole(Role $role): self
    {
        $this->role = $role;
        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): self
    {
        $this->actif = $actif;
        return $this;
    }

    public function getDateCreation(): \DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): self
    {
        $this->resetToken = $resetToken;
        return $this;
    }

    public function getResetTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->resetTokenExpiresAt;
    }

    public function setResetTokenExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->resetTokenExpiresAt = $expiresAt;
        return $this;
    }

    // ═══════════════════════════════════════════════════════════
    // UserInterface — méthodes requises par Symfony Security
    // ═══════════════════════════════════════════════════════════

    /**
     * Identifiant unique de l'utilisateur (utilisé par Symfony).
     * Ici l'e-mail sert de login.
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * Rôles de l'utilisateur au format Symfony (préfixés ROLE_).
     * Grâce à role_hierarchy dans security.yaml, un admin hérite des
     * permissions de l'employé, qui hérite de celles de l'utilisateur.
     */
    public function getRoles(): array
    {
        return [$this->role->toSymfonyRole()];
    }

    /**
     * Efface les données sensibles temporaires (utilisé pour les plaintextPassword).
     * Ici, le mot de passe est déjà haché en base, rien à faire.
     */
    public function eraseCredentials(): void
    {
        // Rien à effacer : on ne conserve pas le mot de passe en clair
    }
}
