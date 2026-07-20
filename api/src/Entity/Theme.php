<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ThemeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Thème d'un menu (Noël, Pâques, classique, évènement).
 * Correspond à la table `theme` du MCD (annexe 1).
 * Relation *propose* du MCD : un menu propose 1..N thèmes.
 */
#[ORM\Entity(repositoryClass: ThemeRepository::class)]
#[ORM\Table(name: 'theme')]
class Theme
{
    public const NOEL       = 'Noël';
    public const PAQUES     = 'Pâques';
    public const CLASSIQUE  = 'classique';
    public const EVENEMENT  = 'évènement';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'theme_id', type: 'integer')]
    private ?int $themeId = null;

    #[ORM\Column(name: 'libelle', type: 'string', length: 50, unique: true)]
    private string $libelle;

    /**
     * Relation *propose* (ManyToMany avec Menu) — côté inverse.
     * @var Collection<int, Menu>
     */
    #[ORM\ManyToMany(targetEntity: Menu::class, mappedBy: 'themes')]
    private Collection $menus;

    public function __construct(string $libelle)
    {
        $this->libelle = $libelle;
        $this->menus = new ArrayCollection();
    }

    public function getThemeId(): ?int
    {
        return $this->themeId;
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
