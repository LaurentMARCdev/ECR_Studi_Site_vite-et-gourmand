<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload attendu pour créer ou modifier un menu.
 *
 * Utilisé par :
 *   - POST /api/employe/menus     (création)
 *   - PUT  /api/employe/menus/:id (modification)
 *
 * Les IDs de plats/thèmes/régimes/allergènes sont résolus côté service.
 */
class MenuDTO
{
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(min: 3, max: 150)]
    public string $titre = '';

    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    public string $description = '';

    #[Assert\NotBlank]
    #[Assert\Positive(message: 'Le prix par personne doit être positif.')]
    public float $prixParPersonne = 0.0;

    #[Assert\NotBlank]
    #[Assert\Positive(message: 'Le nombre minimum de personnes doit être positif.')]
    public int $nombrePersonneMinimum = 1;

    /**
     * NULL = illimité, 0 = épuisé, N = stock disponible.
     */
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Le stock ne peut pas être négatif.')]
    public ?int $quantiteRestante = null;

    public ?string $conditions = null;

    public bool $actif = true;

    /**
     * IDs des thèmes associés (au moins 1 requis).
     * @var int[]
     */
    #[Assert\Count(min: 1, minMessage: 'Sélectionnez au moins un thème.')]
    #[Assert\All([new Assert\Positive()])]
    public array $themeIds = [];

    /**
     * IDs des régimes associés (au moins 1 requis).
     * @var int[]
     */
    #[Assert\Count(min: 1, minMessage: 'Sélectionnez au moins un régime.')]
    #[Assert\All([new Assert\Positive()])]
    public array $regimeIds = [];

    /**
     * IDs des plats associés.
     * @var int[]
     */
    #[Assert\Count(min: 1, minMessage: 'Un menu doit contenir au moins un plat.')]
    #[Assert\All([new Assert\Positive()])]
    public array $platIds = [];

    /**
     * URLs des images (relatif au dossier public).
     * L'upload lui-même se fait via un autre endpoint dédié.
     * @var array<int, array{url: string, altText?: string, ordreAffichage?: int, estPrincipale?: bool}>
     */
    public array $images = [];
}
