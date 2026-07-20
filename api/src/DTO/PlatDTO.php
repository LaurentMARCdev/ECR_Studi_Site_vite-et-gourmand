<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Plat;
use Symfony\Component\Validator\Constraints as Assert;

class PlatDTO
{
    #[Assert\NotBlank(message: 'Le titre du plat est obligatoire.')]
    #[Assert\Length(min: 2, max: 150)]
    public string $titre = '';

    public ?string $description = null;

    #[Assert\NotBlank(message: 'La catégorie est obligatoire.')]
    #[Assert\Choice(choices: Plat::CATEGORIES_AUTORISEES, message: 'Catégorie invalide.')]
    public string $categorie = '';

    public ?string $imageUrl = null;

    /**
     * @var int[]
     */
    #[Assert\All([new Assert\Positive()])]
    public array $allergeneIds = [];
}
