<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PlatRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Plat individuel — correspond à la table `plat` du MCD (annexe 1).
 *
 * Un plat appartient à une catégorie (entrée / plat_principal / dessert)
 * et peut être présent dans plusieurs menus (relation *compose*).
 *
 * ⚠ Décision d'implémentation :
 *   Le MCD stocke `photo` en BLOB. En pratique, on préfère stocker l'image
 *   sur le disque et ne garder que le chemin en base : c'est bien plus performant,
 *   on peut servir les images via un CDN et alléger la BDD.
 *   Le champ s'appelle donc `image_url` (chemin relatif au dossier public).
 */
#[ORM\Entity(repositoryClass: PlatRepository::class)]
#[ORM\Table(name: 'plat')]
class Plat
{
    public const CATEGORIE_ENTREE          = 'entree';
    public const CATEGORIE_PLAT_PRINCIPAL  = 'plat_principal';
    public const CATEGORIE_DESSERT         = 'dessert';

    public const CATEGORIES_AUTORISEES = [
        self::CATEGORIE_ENTREE,
        self::CATEGORIE_PLAT_PRINCIPAL,
        self::CATEGORIE_DESSERT,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'plat_id', type: 'integer')]
    private ?int $platId = null;

    #[ORM\Column(name: 'titre', type: 'string', length: 150)]
    #[Assert\NotBlank(message: 'Le titre du plat est obligatoire.')]
    #[Assert\Length(max: 150)]
    private string $titre;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    /**
     * Catégorie du plat : entrée, plat_principal ou dessert.
     * Contrainte CHECK en base + validation applicative.
     */
    #[ORM\Column(name: 'categorie', type: 'string', length: 20)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: self::CATEGORIES_AUTORISEES, message: 'Catégorie invalide.')]
    private string $categorie;

    /**
     * Chemin relatif vers l'image (ex: /uploads/plats/plat-1.jpg).
     * NULL si aucune photo.
     */
    #[ORM\Column(name: 'image_url', type: 'string', length: 255, nullable: true)]
    private ?string $imageUrl = null;

    /**
     * Relation *contient* (ManyToMany avec Allergene) — côté propriétaire.
     * @var Collection<int, Allergene>
     */
    #[ORM\ManyToMany(targetEntity: Allergene::class, inversedBy: 'plats')]
    #[ORM\JoinTable(name: 'plat_allergene')]
    #[ORM\JoinColumn(name: 'plat_id',      referencedColumnName: 'plat_id',      onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'allergene_id', referencedColumnName: 'allergene_id', onDelete: 'CASCADE')]
    private Collection $allergenes;

    /**
     * Relation *compose* (ManyToMany inverse — côté Menu propriétaire).
     * @var Collection<int, Menu>
     */
    #[ORM\ManyToMany(targetEntity: Menu::class, mappedBy: 'plats')]
    private Collection $menus;

    public function __construct()
    {
        $this->allergenes = new ArrayCollection();
        $this->menus      = new ArrayCollection();
    }

    public function getPlatId(): ?int
    {
        return $this->platId;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCategorie(): string
    {
        return $this->categorie;
    }

    public function setCategorie(string $categorie): self
    {
        if (!in_array($categorie, self::CATEGORIES_AUTORISEES, true)) {
            throw new \InvalidArgumentException("Catégorie invalide : $categorie");
        }
        $this->categorie = $categorie;
        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): self
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    /** @return Collection<int, Allergene> */
    public function getAllergenes(): Collection
    {
        return $this->allergenes;
    }

    public function addAllergene(Allergene $allergene): self
    {
        if (!$this->allergenes->contains($allergene)) {
            $this->allergenes->add($allergene);
        }
        return $this;
    }

    public function removeAllergene(Allergene $allergene): self
    {
        $this->allergenes->removeElement($allergene);
        return $this;
    }

    public function clearAllergenes(): self
    {
        $this->allergenes->clear();
        return $this;
    }

    /** @return Collection<int, Menu> */
    public function getMenus(): Collection
    {
        return $this->menus;
    }
}
