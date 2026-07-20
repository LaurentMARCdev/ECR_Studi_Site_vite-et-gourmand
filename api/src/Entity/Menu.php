<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\MenuRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Menu — entité centrale du domaine (table `menu` du MCD, annexe 1).
 *
 * Relations :
 *   - propose  : ManyToMany avec Theme     (côté propriétaire)
 *   - adopte   : ManyToMany avec Regime    (côté propriétaire)
 *   - compose  : ManyToMany avec Plat      (côté propriétaire)
 *   - possede  : OneToMany  vers ImageMenu (galerie)
 *
 * Règles métier :
 *   - nombre_personne_minimum ≥ 1
 *   - prix_par_personne > 0
 *   - quantite_restante : NULL = illimité, 0 = épuisé, N = stock disponible
 *   - actif : soft delete (permet à un employé de "supprimer" un menu
 *             tout en gardant l'historique des commandes intact)
 */
#[ORM\Entity(repositoryClass: MenuRepository::class)]
#[ORM\Table(name: 'menu')]
#[ORM\HasLifecycleCallbacks]
class Menu
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'menu_id', type: 'integer')]
    private ?int $menuId = null;

    #[ORM\Column(name: 'titre', type: 'string', length: 150)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(max: 150)]
    private string $titre;

    #[ORM\Column(name: 'description', type: 'text')]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    private string $description;

    /**
     * Prix par personne au moment du dernier prix fixé.
     * On stocke en DECIMAL(8,2) pour éviter les erreurs d'arrondi de float.
     */
    #[ORM\Column(name: 'prix_par_personne', type: 'decimal', precision: 8, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive(message: 'Le prix par personne doit être positif.')]
    private string $prixParPersonne;

    /**
     * Nombre minimum de personnes requises pour commander ce menu.
     * L'énoncé impose de commander pour ≥ ce nombre.
     */
    #[ORM\Column(name: 'nombre_personne_minimum', type: 'smallint')]
    #[Assert\NotBlank]
    #[Assert\Positive(message: 'Le nombre minimum de personnes doit être positif.')]
    private int $nombrePersonneMinimum;

    /**
     * Stock restant :
     *   - NULL    : stock illimité (ex: menu classique disponible en permanence)
     *   - 0       : épuisé, commande impossible
     *   - N > 0   : nombre de commandes possibles restantes
     */
    #[ORM\Column(name: 'quantite_restante', type: 'integer', nullable: true)]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le stock ne peut pas être négatif.')]
    private ?int $quantiteRestante = null;

    /**
     * Conditions particulières (délai de commande, précautions de stockage, etc.).
     * Le cahier des charges impose de les mettre en évidence sur la page détail.
     */
    #[ORM\Column(name: 'conditions', type: 'text', nullable: true)]
    private ?string $conditions = null;

    /**
     * Soft delete : un employé peut désactiver un menu sans casser
     * l'intégrité des commandes qui le référencent.
     */
    #[ORM\Column(name: 'actif', type: 'boolean', options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(name: 'date_creation', type: 'datetime_immutable')]
    private \DateTimeImmutable $dateCreation;

    #[ORM\Column(name: 'date_modification', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $dateModification = null;

    // ═══════════════════════════════════════════════════════════
    // Relations
    // ═══════════════════════════════════════════════════════════

    /**
     * Relation *propose* (ManyToMany avec Theme) — côté propriétaire.
     * @var Collection<int, Theme>
     */
    #[ORM\ManyToMany(targetEntity: Theme::class, inversedBy: 'menus')]
    #[ORM\JoinTable(name: 'menu_theme')]
    #[ORM\JoinColumn(name: 'menu_id',  referencedColumnName: 'menu_id',  onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'theme_id', referencedColumnName: 'theme_id', onDelete: 'CASCADE')]
    private Collection $themes;

    /**
     * Relation *adopte* (ManyToMany avec Regime) — côté propriétaire.
     * @var Collection<int, Regime>
     */
    #[ORM\ManyToMany(targetEntity: Regime::class, inversedBy: 'menus')]
    #[ORM\JoinTable(name: 'menu_regime')]
    #[ORM\JoinColumn(name: 'menu_id',   referencedColumnName: 'menu_id',   onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'regime_id', referencedColumnName: 'regime_id', onDelete: 'CASCADE')]
    private Collection $regimes;

    /**
     * Relation *compose* (ManyToMany avec Plat) — côté propriétaire.
     * @var Collection<int, Plat>
     */
    #[ORM\ManyToMany(targetEntity: Plat::class, inversedBy: 'menus')]
    #[ORM\JoinTable(name: 'menu_plat')]
    #[ORM\JoinColumn(name: 'menu_id', referencedColumnName: 'menu_id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'plat_id', referencedColumnName: 'plat_id', onDelete: 'CASCADE')]
    private Collection $plats;

    /**
     * Galerie d'images.
     * @var Collection<int, ImageMenu>
     */
    #[ORM\OneToMany(targetEntity: ImageMenu::class, mappedBy: 'menu', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordreAffichage' => 'ASC'])]
    private Collection $images;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
        $this->themes  = new ArrayCollection();
        $this->regimes = new ArrayCollection();
        $this->plats   = new ArrayCollection();
        $this->images  = new ArrayCollection();
    }

    // ═══════════════════════════════════════════════════════════
    // Lifecycle callbacks
    // ═══════════════════════════════════════════════════════════

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->dateModification = new \DateTimeImmutable();
    }

    // ═══════════════════════════════════════════════════════════
    // Getters / Setters
    // ═══════════════════════════════════════════════════════════

    public function getMenuId(): ?int
    {
        return $this->menuId;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): self
    {
        $this->titre = trim($titre);
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getPrixParPersonne(): string
    {
        return $this->prixParPersonne;
    }

    /**
     * Accepte int, float ou string — normalise en string décimale à 2 décimales
     * pour éviter les erreurs d'arrondi.
     */
    public function setPrixParPersonne(int|float|string $prix): self
    {
        $this->prixParPersonne = number_format((float)$prix, 2, '.', '');
        return $this;
    }

    public function getPrixParPersonneAsFloat(): float
    {
        return (float)$this->prixParPersonne;
    }

    public function getNombrePersonneMinimum(): int
    {
        return $this->nombrePersonneMinimum;
    }

    public function setNombrePersonneMinimum(int $nombre): self
    {
        $this->nombrePersonneMinimum = $nombre;
        return $this;
    }

    public function getQuantiteRestante(): ?int
    {
        return $this->quantiteRestante;
    }

    public function setQuantiteRestante(?int $quantite): self
    {
        $this->quantiteRestante = $quantite;
        return $this;
    }

    public function getConditions(): ?string
    {
        return $this->conditions;
    }

    public function setConditions(?string $conditions): self
    {
        $this->conditions = $conditions;
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

    public function getDateModification(): ?\DateTimeImmutable
    {
        return $this->dateModification;
    }

    // ═══════════════════════════════════════════════════════════
    // Gestion des thèmes (relation *propose*)
    // ═══════════════════════════════════════════════════════════

    /** @return Collection<int, Theme> */
    public function getThemes(): Collection
    {
        return $this->themes;
    }

    public function addTheme(Theme $theme): self
    {
        if (!$this->themes->contains($theme)) {
            $this->themes->add($theme);
        }
        return $this;
    }

    public function removeTheme(Theme $theme): self
    {
        $this->themes->removeElement($theme);
        return $this;
    }

    public function clearThemes(): self
    {
        $this->themes->clear();
        return $this;
    }

    // ═══════════════════════════════════════════════════════════
    // Gestion des régimes (relation *adopte*)
    // ═══════════════════════════════════════════════════════════

    /** @return Collection<int, Regime> */
    public function getRegimes(): Collection
    {
        return $this->regimes;
    }

    public function addRegime(Regime $regime): self
    {
        if (!$this->regimes->contains($regime)) {
            $this->regimes->add($regime);
        }
        return $this;
    }

    public function removeRegime(Regime $regime): self
    {
        $this->regimes->removeElement($regime);
        return $this;
    }

    public function clearRegimes(): self
    {
        $this->regimes->clear();
        return $this;
    }

    // ═══════════════════════════════════════════════════════════
    // Gestion des plats (relation *compose*)
    // ═══════════════════════════════════════════════════════════

    /** @return Collection<int, Plat> */
    public function getPlats(): Collection
    {
        return $this->plats;
    }

    public function addPlat(Plat $plat): self
    {
        if (!$this->plats->contains($plat)) {
            $this->plats->add($plat);
        }
        return $this;
    }

    public function removePlat(Plat $plat): self
    {
        $this->plats->removeElement($plat);
        return $this;
    }

    public function clearPlats(): self
    {
        $this->plats->clear();
        return $this;
    }

    /**
     * Regroupe les plats par catégorie (entrée / plat_principal / dessert)
     * pour l'affichage sur la page détail.
     *
     * @return array{entrees: Plat[], plats_principaux: Plat[], desserts: Plat[]}
     */
    public function getPlatsGroupes(): array
    {
        $groupes = ['entrees' => [], 'plats_principaux' => [], 'desserts' => []];

        foreach ($this->plats as $plat) {
            match ($plat->getCategorie()) {
                Plat::CATEGORIE_ENTREE          => $groupes['entrees'][]          = $plat,
                Plat::CATEGORIE_PLAT_PRINCIPAL  => $groupes['plats_principaux'][] = $plat,
                Plat::CATEGORIE_DESSERT         => $groupes['desserts'][]         = $plat,
                default                          => null,
            };
        }

        return $groupes;
    }

    // ═══════════════════════════════════════════════════════════
    // Gestion des images
    // ═══════════════════════════════════════════════════════════

    /** @return Collection<int, ImageMenu> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(ImageMenu $image): self
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setMenu($this);
        }
        return $this;
    }

    public function removeImage(ImageMenu $image): self
    {
        $this->images->removeElement($image);
        return $this;
    }

    public function getImagePrincipale(): ?ImageMenu
    {
        foreach ($this->images as $image) {
            if ($image->isPrincipale()) {
                return $image;
            }
        }
        // Fallback : première image de la galerie
        return $this->images->first() ?: null;
    }

    // ═══════════════════════════════════════════════════════════
    // Règles métier
    // ═══════════════════════════════════════════════════════════

    /**
     * Le menu est-il disponible pour la commande ?
     */
    public function estDisponible(): bool
    {
        return $this->actif && ($this->quantiteRestante === null || $this->quantiteRestante > 0);
    }

    /**
     * Décrémente le stock de 1 lors d'une commande.
     * Ne fait rien si le stock est illimité (NULL).
     *
     * @throws \DomainException si le menu est épuisé
     */
    public function decrementerStock(): void
    {
        if ($this->quantiteRestante === null) {
            return; // Stock illimité, rien à faire
        }
        if ($this->quantiteRestante <= 0) {
            throw new \DomainException('Menu épuisé.');
        }
        $this->quantiteRestante--;
    }

    /**
     * Incrémente le stock de 1 (utilisé lors d'une annulation de commande).
     */
    public function incrementerStock(): void
    {
        if ($this->quantiteRestante !== null) {
            $this->quantiteRestante++;
        }
    }

    /**
     * Prix total du menu au nombre minimum de personnes (prix "à partir de").
     */
    public function getPrixMinimum(): float
    {
        return $this->getPrixParPersonneAsFloat() * $this->nombrePersonneMinimum;
    }
}
