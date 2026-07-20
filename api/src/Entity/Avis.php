<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\StatutAvis;
use App\Repository\AvisRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Avis client — table `avis` du MCD (annexe 1).
 *
 * ─────────────────────────────────────────────────────────────
 * RÈGLES MÉTIER (extraites du sujet)
 * ─────────────────────────────────────────────────────────────
 * 1. Note entre 1 et 5 (étoiles)
 * 2. Un avis est lié à une commande TERMINÉE (règle appliquée dans le service)
 * 3. Un utilisateur ne peut déposer QU'UN SEUL avis par commande
 * 4. Modération obligatoire (en_attente → valide | refuse)
 * 5. Seuls les avis validés apparaissent sur la page d'accueil
 * ─────────────────────────────────────────────────────────────
 *
 * Relation MCD *publie* : utilisateur (0,N) → avis (1,1)
 * Lien commande : permet de retrouver le menu concerné + protéger
 *                 contre les avis déposés sans avoir commandé.
 */
#[ORM\Entity(repositoryClass: AvisRepository::class)]
#[ORM\Table(name: 'avis')]
#[ORM\UniqueConstraint(name: 'unique_avis_commande', columns: ['commande_id'])]
class Avis
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'avis_id', type: 'integer')]
    private ?int $avisId = null;

    /**
     * Auteur (relation *publie* du MCD).
     */
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'utilisateur_id', referencedColumnName: 'utilisateur_id', nullable: false, onDelete: 'CASCADE')]
    private Utilisateur $utilisateur;

    /**
     * Commande à laquelle l'avis se rapporte.
     * Une commande = un seul avis (contrainte unique en base).
     */
    #[ORM\ManyToOne(targetEntity: Commande::class)]
    #[ORM\JoinColumn(name: 'commande_id', referencedColumnName: 'commande_id', nullable: false, onDelete: 'CASCADE')]
    private Commande $commande;

    #[ORM\Column(name: 'note', type: 'smallint')]
    #[Assert\NotBlank]
    #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'La note doit être comprise entre {{ min }} et {{ max }} étoiles.')]
    private int $note;

    #[ORM\Column(name: 'commentaire', type: 'text')]
    #[Assert\NotBlank(message: 'Le commentaire est obligatoire.')]
    #[Assert\Length(
        min: 10,
        max: 2000,
        minMessage: 'Le commentaire doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le commentaire ne peut pas dépasser {{ limit }} caractères.',
    )]
    private string $commentaire;

    #[ORM\Column(name: 'statut', type: 'string', enumType: StatutAvis::class, length: 15)]
    private StatutAvis $statut = StatutAvis::EN_ATTENTE;

    #[ORM\Column(name: 'date_creation', type: 'datetime_immutable')]
    private \DateTimeImmutable $dateCreation;

    #[ORM\Column(name: 'date_moderation', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateModeration = null;

    /**
     * Modérateur (employé/admin) qui a validé ou refusé l'avis.
     * Optionnel — permet la traçabilité.
     */
    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'moderateur_id', referencedColumnName: 'utilisateur_id', nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $moderateur = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
    }

    // ═══════════════════════════════════════════════════════════
    // MÉTHODES MÉTIER
    // ═══════════════════════════════════════════════════════════

    public function valider(Utilisateur $moderateur): void
    {
        $this->statut         = StatutAvis::VALIDE;
        $this->moderateur     = $moderateur;
        $this->dateModeration = new \DateTimeImmutable();
    }

    public function refuser(Utilisateur $moderateur): void
    {
        $this->statut         = StatutAvis::REFUSE;
        $this->moderateur     = $moderateur;
        $this->dateModeration = new \DateTimeImmutable();
    }

    public function estEnAttente(): bool
    {
        return $this->statut === StatutAvis::EN_ATTENTE;
    }

    // ═══════════════════════════════════════════════════════════
    // GETTERS / SETTERS
    // ═══════════════════════════════════════════════════════════

    public function getAvisId(): ?int { return $this->avisId; }

    public function getUtilisateur(): Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(Utilisateur $u): self { $this->utilisateur = $u; return $this; }

    public function getCommande(): Commande { return $this->commande; }
    public function setCommande(Commande $c): self { $this->commande = $c; return $this; }

    public function getNote(): int { return $this->note; }
    public function setNote(int $n): self { $this->note = $n; return $this; }

    public function getCommentaire(): string { return $this->commentaire; }
    public function setCommentaire(string $c): self { $this->commentaire = trim($c); return $this; }

    public function getStatut(): StatutAvis { return $this->statut; }
    public function setStatut(StatutAvis $s): self { $this->statut = $s; return $this; }

    public function getDateCreation(): \DateTimeImmutable { return $this->dateCreation; }
    public function getDateModeration(): ?\DateTimeImmutable { return $this->dateModeration; }

    public function getModerateur(): ?Utilisateur { return $this->moderateur; }
}
