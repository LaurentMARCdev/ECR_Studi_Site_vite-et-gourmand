<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AllergeneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Allergène présent dans un plat.
 * Correspond à la table `allergene` du MCD (annexe 1).
 * Relation *contient* du MCD : un plat contient 0..N allergènes.
 *
 * Basé sur les 14 allergènes majeurs réglementés (règlement INCO n°1169/2011).
 */
#[ORM\Entity(repositoryClass: AllergeneRepository::class)]
#[ORM\Table(name: 'allergene')]
class Allergene
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'allergene_id', type: 'integer')]
    private ?int $allergeneId = null;

    #[ORM\Column(name: 'libelle', type: 'string', length: 50, unique: true)]
    private string $libelle;

    /**
     * Relation *contient* (ManyToMany avec Plat) — côté inverse.
     * @var Collection<int, Plat>
     */
    #[ORM\ManyToMany(targetEntity: Plat::class, mappedBy: 'allergenes')]
    private Collection $plats;

    public function __construct(string $libelle)
    {
        $this->libelle = $libelle;
        $this->plats = new ArrayCollection();
    }

    public function getAllergeneId(): ?int
    {
        return $this->allergeneId;
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

    /** @return Collection<int, Plat> */
    public function getPlats(): Collection
    {
        return $this->plats;
    }
}
