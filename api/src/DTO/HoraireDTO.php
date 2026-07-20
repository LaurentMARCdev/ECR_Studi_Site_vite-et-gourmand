<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload pour un seul horaire.
 * Utilisé dans le tableau envoyé par PUT /api/employe/horaires.
 */
class HoraireDTO
{
    #[Assert\NotBlank]
    #[Assert\Positive]
    public int $horaireId = 0;

    public bool $ferme = false;

    /**
     * Format attendu : "HH:MM" ou "" (vide si fermé).
     */
    #[Assert\Regex(
        pattern: '/^(([01]\d|2[0-3]):[0-5]\d)?$/',
        message: 'Format d\'heure invalide (HH:MM attendu).'
    )]
    public ?string $heureOuverture = null;

    #[Assert\Regex(
        pattern: '/^(([01]\d|2[0-3]):[0-5]\d)?$/',
        message: 'Format d\'heure invalide (HH:MM attendu).'
    )]
    public ?string $heureFermeture = null;
}
