<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ImageMenuRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Image associée à un menu (galerie).
 *
 * Le MCD prévoit "une galerie d'image" mais sans détailler la structure.
 * Nous créons une table dédiée (mieux qu'un champ JSON) permettant :
 *  - un ordre d'affichage explicite
 *  - une image principale (isPrincipale)
 *  - un alt text pour l'accessibilité (RGAA)
 *  - une suppression indépendante
 */
#[ORM\Entity(repositoryClass: ImageMenuRepository::class)]
#[ORM\Table(name: 'image_menu')]
class ImageMenu
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'image_id', type: 'integer')]
    private ?int $imageId = null;

    #[ORM\ManyToOne(targetEntity: Menu::class, inversedBy: 'images')]
    #[ORM\JoinColumn(name: 'menu_id', referencedColumnName: 'menu_id', nullable: false, onDelete: 'CASCADE')]
    private Menu $menu;

    /**
     * Chemin relatif au dossier public (ex: /uploads/menus/menu-1-photo-a.jpg).
     */
    #[ORM\Column(name: 'url', type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $url;

    /**
     * Texte alternatif accessible (RGAA).
     */
    #[ORM\Column(name: 'alt_text', type: 'string', length: 255, nullable: true)]
    private ?string $altText = null;

    #[ORM\Column(name: 'ordre_affichage', type: 'smallint', options: ['default' => 0])]
    private int $ordreAffichage = 0;

    #[ORM\Column(name: 'est_principale', type: 'boolean', options: ['default' => false])]
    private bool $estPrincipale = false;

    public function getImageId(): ?int
    {
        return $this->imageId;
    }

    public function getMenu(): Menu
    {
        return $this->menu;
    }

    public function setMenu(Menu $menu): self
    {
        $this->menu = $menu;
        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function getAltText(): ?string
    {
        return $this->altText;
    }

    public function setAltText(?string $altText): self
    {
        $this->altText = $altText;
        return $this;
    }

    public function getOrdreAffichage(): int
    {
        return $this->ordreAffichage;
    }

    public function setOrdreAffichage(int $ordre): self
    {
        $this->ordreAffichage = $ordre;
        return $this;
    }

    public function isPrincipale(): bool
    {
        return $this->estPrincipale;
    }

    public function setEstPrincipale(bool $estPrincipale): self
    {
        $this->estPrincipale = $estPrincipale;
        return $this;
    }
}
