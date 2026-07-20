<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload pour la modification d'une commande par son utilisateur.
 * PUT /api/commandes/{numero}/modifier
 *
 * Note importante du sujet :
 *   "tout est modifiable, sauf, le choix du menu"
 * → Il n'y a donc PAS de menuId dans ce DTO.
 */
class ModifierCommandeDTO
{
    #[Assert\NotBlank]
    #[Assert\Positive]
    public int $nombrePersonnes = 0;

    #[Assert\NotBlank]
    #[Assert\Date]
    public string $datePrestation = '';

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^([01]\d|2[0-3]):[0-5]\d$/')]
    public string $heureLivraison = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $adresseLivraison = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $villeLivraison = '';

    public ?bool $pretMateriel = null;
}
