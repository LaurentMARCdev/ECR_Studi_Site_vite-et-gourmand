<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RegimeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Régime alimentaire (classique, végétarien, vegan, sans gluten...).
 * Correspond à la table `regime` du MCD (annexe 1).
 * Relation *adopte* du MCD : un menu adopte 1..N régimes.
 *
 * Le libellé est extensible : l'énoncé indique "vous pouvez alimenter
 * davantage cette catégorie" — c'est pour ça que c'est une table dédiée
 * et pas un enum figé.
 */
#[ORM\Entity(repositoryClass: RegimeRepository::class)]
#[ORM\Table(name: 'regime')]
class Regime
{
    public const CLASSIQUE  = 'classique';
    public const VEGETARIEN = 'végétarien';
    public const VEGAN      = 'vegan';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'regime_id', type: 'integer')]
    private ?int $regimeId = null;

    #[ORM\Column(name: 'libelle', type: 'string', length: 50, unique: true)]
    private string $libelle;

    /**
     * Relation *adopte* (ManyToMany avec Menu) — côté inverse.
     * @var Collection<int, Menu>
     */
    #[ORM\ManyToMany(targetEntity: Menu::class, mappedBy: 'regimes')]
    private Collection $menus;

    public function __construct(string $libelle)
    {
        $this->libelle = $libelle;
        $this->menus = new ArrayCollection();
    }

    public function getRegimeId(): ?int
    {
        return $this->regimeId;
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

    /** @return Collection<int, Menu> */
    public function getMenus(): Collection
    {
        return $this->menus;
    }
}
