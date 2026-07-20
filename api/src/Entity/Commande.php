<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\StatutCommande;
use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Commande — table `commande` du MCD (annexe 1).
 *
 * ─────────────────────────────────────────────────────────────
 * RÈGLES MÉTIER (extraites du sujet)
 * ─────────────────────────────────────────────────────────────
 * 1. Prix menu = prix_par_personne × nombre_personnes
 * 2. Réduction 10% automatique si nombre_personnes ≥ menu.minimum + 5
 * 3. Livraison : 5 € de base, + 0,59 €/km si hors Bordeaux
 * 4. Cycle de vie : voir StatutCommande (transitions strictes)
 * 5. Numéro de commande unique format VG-YYYY-NNNN
 * 6. Décrémentation du stock à la création, incrément à l'annulation
 * ─────────────────────────────────────────────────────────────
 */
#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[ORM\Table(name: 'commande')]
#[ORM\HasLifecycleCallbacks]
class Commande
{
    /**
     * Base pour le calcul de la livraison — parfaitement scalaire :
     * si le tarif change, une seule constante à modifier.
     */
    public const FRAIS_LIVRAISON_BASE_EUROS      = 5.00;
    public const FRAIS_LIVRAISON_PAR_KM_EUROS    = 0.59;
    public const VILLE_LIVRAISON_GRATUITE        = 'Bordeaux';
    public const SEUIL_REDUCTION_PERSONNES_SUPPL = 5;    // +5 personnes au-delà du minimum
    public const TAUX_REDUCTION                  = 0.10; // 10%

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'commande_id', type: 'integer')]
    private ?int $commandeId = null;

    /**
     * Identifiant public affiché au client (format VG-2025-0042).
     */
    #[ORM\Column(name: 'numero_commande', type: 'string', length: 20, unique: true)]
    private string $numeroCommande;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'utilisateur_id', referencedColumnName: 'utilisateur_id', nullable: false)]
    private Utilisateur $utilisateur;

    #[ORM\ManyToOne(targetEntity: Menu::class)]
    #[ORM\JoinColumn(name: 'menu_id', referencedColumnName: 'menu_id', nullable: false)]
    private Menu $menu;

    // ═══════════════════════════════════════════════════════════
    // Détails de la prestation
    // ═══════════════════════════════════════════════════════════

    #[ORM\Column(name: 'nombre_personnes', type: 'smallint')]
    #[Assert\Positive]
    private int $nombrePersonnes;

    #[ORM\Column(name: 'date_prestation', type: 'date_immutable')]
    #[Assert\NotBlank]
    private \DateTimeImmutable $datePrestation;

    #[ORM\Column(name: 'heure_livraison', type: 'time_immutable')]
    #[Assert\NotBlank]
    private \DateTimeImmutable $heureLivraison;

    #[ORM\Column(name: 'adresse_livraison', type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'L\'adresse de livraison est obligatoire.')]
    private string $adresseLivraison;

    #[ORM\Column(name: 'ville_livraison', type: 'string', length: 100)]
    #[Assert\NotBlank]
    private string $villeLivraison;

    /**
     * Distance en km depuis Bordeaux (calculée à la validation).
     * Zéro si livraison dans Bordeaux.
     */
    #[ORM\Column(name: 'distance_km', type: 'decimal', precision: 6, scale: 2, options: ['default' => 0])]
    private string $distanceKm = '0.00';

    // ═══════════════════════════════════════════════════════════
    // Prix (figés au moment de la commande — historique préservé)
    // ═══════════════════════════════════════════════════════════

    #[ORM\Column(name: 'prix_menu', type: 'decimal', precision: 10, scale: 2)]
    private string $prixMenu;

    #[ORM\Column(name: 'reduction', type: 'decimal', precision: 10, scale: 2, options: ['default' => 0])]
    private string $reduction = '0.00';

    #[ORM\Column(name: 'prix_livraison', type: 'decimal', precision: 8, scale: 2)]
    private string $prixLivraison;

    #[ORM\Column(name: 'prix_total', type: 'decimal', precision: 10, scale: 2)]
    private string $prixTotal;

    // ═══════════════════════════════════════════════════════════
    // Statut & cycle de vie
    // ═══════════════════════════════════════════════════════════

    #[ORM\Column(name: 'statut', type: 'string', enumType: StatutCommande::class, length: 30)]
    private StatutCommande $statut = StatutCommande::EN_ATTENTE;

    #[ORM\Column(name: 'pret_materiel', type: 'boolean', options: ['default' => false])]
    private bool $pretMateriel = false;

    #[ORM\Column(name: 'restitution_materiel', type: 'boolean', options: ['default' => false])]
    private bool $restitutionMateriel = false;

    #[ORM\Column(name: 'motif_annulation', type: 'text', nullable: true)]
    private ?string $motifAnnulation = null;

    #[ORM\Column(name: 'mode_contact_annulation', type: 'string', length: 10, nullable: true)]
    private ?string $modeContactAnnulation = null;

    /**
     * Historique des transitions de statut — table dédiée
     * pour permettre l'affichage timeline côté utilisateur.
     *
     * @var Collection<int, HistoriqueStatut>
     */
    #[ORM\OneToMany(
        targetEntity: HistoriqueStatut::class,
        mappedBy: 'commande',
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    #[ORM\OrderBy(['dateChangement' => 'ASC'])]
    private Collection $historiqueStatuts;

    #[ORM\Column(name: 'date_commande', type: 'datetime_immutable')]
    private \DateTimeImmutable $dateCommande;

    #[ORM\Column(name: 'date_modification', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateModification = null;

    public function __construct()
    {
        $this->dateCommande = new \DateTimeImmutable();
        $this->historiqueStatuts = new ArrayCollection();
    }

    // ═══════════════════════════════════════════════════════════
    // Lifecycle
    // ═══════════════════════════════════════════════════════════

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->dateModification = new \DateTimeImmutable();
    }

    // ═══════════════════════════════════════════════════════════
    // MÉTHODES MÉTIER — calcul du prix
    // ═══════════════════════════════════════════════════════════

    /**
     * Recalcule tous les prix depuis les données actuelles.
     * Appelé à la création et à chaque modification.
     *
     * @throws \DomainException si nombre_personnes < menu.minimum
     */
    public function recalculerPrix(): void
    {
        if ($this->nombrePersonnes < $this->menu->getNombrePersonneMinimum()) {
            throw new \DomainException(sprintf(
                'Ce menu nécessite un minimum de %d personnes (%d demandées).',
                $this->menu->getNombrePersonneMinimum(),
                $this->nombrePersonnes
            ));
        }

        $prixMenu = $this->menu->getPrixParPersonneAsFloat() * $this->nombrePersonnes;
        $this->prixMenu = number_format($prixMenu, 2, '.', '');

        $reduction = 0.0;
        if ($this->autoriseReduction()) {
            $reduction = $prixMenu * self::TAUX_REDUCTION;
        }
        $this->reduction = number_format($reduction, 2, '.', '');

        $prixLivraison = $this->calculerFraisLivraison();
        $this->prixLivraison = number_format($prixLivraison, 2, '.', '');

        $total = $prixMenu - $reduction + $prixLivraison;
        $this->prixTotal = number_format($total, 2, '.', '');
    }

    /**
     * "réduction de 10% est appliquée pour toutes commandes ayant 5 personnes
     *  de plus que le nombre de personnes minimum indiqué dans le menu"
     */
    public function autoriseReduction(): bool
    {
        return $this->nombrePersonnes
            >= $this->menu->getNombrePersonneMinimum() + self::SEUIL_REDUCTION_PERSONNES_SUPPL;
    }

    /**
     * "facturation de 5 euros (majoré de 59 centimes par kilomètre parcouru)
     *  si la livraison n'est pas dans la ville de Bordeaux".
     */
    public function calculerFraisLivraison(): float
    {
        if ($this->estLivraisonBordeaux()) {
            return 0.0;
        }
        return self::FRAIS_LIVRAISON_BASE_EUROS
            + ((float)$this->distanceKm * self::FRAIS_LIVRAISON_PAR_KM_EUROS);
    }

    public function estLivraisonBordeaux(): bool
    {
        return strcasecmp(trim($this->villeLivraison), self::VILLE_LIVRAISON_GRATUITE) === 0;
    }

    // ═══════════════════════════════════════════════════════════
    // MÉTHODES MÉTIER — statut
    // ═══════════════════════════════════════════════════════════

    /**
     * Transitionne vers un nouveau statut si autorisé.
     * Historise le changement.
     *
     * @throws \DomainException si transition non autorisée
     */
    public function changerStatut(StatutCommande $nouveauStatut): void
    {
        if ($this->statut === $nouveauStatut) {
            return;
        }
        if (!in_array($nouveauStatut, $this->statut->transitionsAutorisees(), true)) {
            throw new \DomainException(sprintf(
                'Transition non autorisée : %s → %s',
                $this->statut->label(),
                $nouveauStatut->label()
            ));
        }
        $this->statut = $nouveauStatut;
        $this->historiserChangement($nouveauStatut);
    }

    /**
     * Enregistre une annulation avec les infos réglementaires
     * (mode de contact + motif obligatoires côté employé).
     */
    public function annuler(string $modeContact, string $motif): void
    {
        if (!in_array($modeContact, ['gsm', 'mail'], true)) {
            throw new \InvalidArgumentException('Mode de contact invalide (gsm ou mail attendu).');
        }
        if (trim($motif) === '') {
            throw new \InvalidArgumentException('Le motif d\'annulation est obligatoire.');
        }
        $this->changerStatut(StatutCommande::ANNULEE);
        $this->modeContactAnnulation = $modeContact;
        $this->motifAnnulation       = $motif;
    }

    /**
     * Utilisé pour l'annulation par l'utilisateur (sans motif obligatoire).
     */
    public function annulerParUtilisateur(): void
    {
        $this->changerStatut(StatutCommande::ANNULEE);
    }

    private function historiserChangement(StatutCommande $statut): void
    {
        $histo = new HistoriqueStatut();
        $histo->setCommande($this)
              ->setStatut($statut)
              ->setDateChangement(new \DateTimeImmutable());
        $this->historiqueStatuts->add($histo);
    }

    // ═══════════════════════════════════════════════════════════
    // Génération du numéro de commande
    // ═══════════════════════════════════════════════════════════

    /**
     * Génère un numéro au format VG-YYYY-NNNN.
     */
    public static function generateNumero(int $sequentiel, ?int $annee = null): string
    {
        $annee ??= (int)(new \DateTimeImmutable())->format('Y');
        return sprintf('VG-%d-%04d', $annee, $sequentiel);
    }

    // ═══════════════════════════════════════════════════════════
    // GETTERS / SETTERS
    // ═══════════════════════════════════════════════════════════

    public function getCommandeId(): ?int { return $this->commandeId; }
    public function getNumeroCommande(): string { return $this->numeroCommande; }
    public function setNumeroCommande(string $numero): self { $this->numeroCommande = $numero; return $this; }

    public function getUtilisateur(): Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(Utilisateur $u): self { $this->utilisateur = $u; return $this; }

    public function getMenu(): Menu { return $this->menu; }
    public function setMenu(Menu $m): self { $this->menu = $m; return $this; }

    public function getNombrePersonnes(): int { return $this->nombrePersonnes; }
    public function setNombrePersonnes(int $n): self { $this->nombrePersonnes = $n; return $this; }

    public function getDatePrestation(): \DateTimeImmutable { return $this->datePrestation; }
    public function setDatePrestation(\DateTimeImmutable $d): self { $this->datePrestation = $d; return $this; }

    public function getHeureLivraison(): \DateTimeImmutable { return $this->heureLivraison; }
    public function setHeureLivraison(\DateTimeImmutable $h): self { $this->heureLivraison = $h; return $this; }

    public function getAdresseLivraison(): string { return $this->adresseLivraison; }
    public function setAdresseLivraison(string $a): self { $this->adresseLivraison = trim($a); return $this; }

    public function getVilleLivraison(): string { return $this->villeLivraison; }
    public function setVilleLivraison(string $v): self { $this->villeLivraison = trim($v); return $this; }

    public function getDistanceKm(): float { return (float)$this->distanceKm; }
    public function setDistanceKm(float $km): self
    {
        $this->distanceKm = number_format(max(0, $km), 2, '.', '');
        return $this;
    }

    public function getPrixMenu(): float      { return (float)$this->prixMenu; }
    public function getReduction(): float     { return (float)$this->reduction; }
    public function getPrixLivraison(): float { return (float)$this->prixLivraison; }
    public function getPrixTotal(): float     { return (float)$this->prixTotal; }

    public function getStatut(): StatutCommande { return $this->statut; }

    /**
     * Utilisé UNIQUEMENT à la création — pas de transition à valider.
     */
    public function setStatutInitial(StatutCommande $s): self
    {
        $this->statut = $s;
        $this->historiserChangement($s);
        return $this;
    }

    public function isPretMateriel(): bool { return $this->pretMateriel; }
    public function setPretMateriel(bool $p): self { $this->pretMateriel = $p; return $this; }

    public function isRestitutionMateriel(): bool { return $this->restitutionMateriel; }
    public function setRestitutionMateriel(bool $r): self { $this->restitutionMateriel = $r; return $this; }

    public function getMotifAnnulation(): ?string { return $this->motifAnnulation; }
    public function getModeContactAnnulation(): ?string { return $this->modeContactAnnulation; }

    /** @return Collection<int, HistoriqueStatut> */
    public function getHistoriqueStatuts(): Collection { return $this->historiqueStatuts; }

    public function getDateCommande(): \DateTimeImmutable { return $this->dateCommande; }
    public function getDateModification(): ?\DateTimeImmutable { return $this->dateModification; }
}
