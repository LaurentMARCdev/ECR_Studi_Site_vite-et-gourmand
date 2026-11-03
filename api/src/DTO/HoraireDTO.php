<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Attribute\SerializedName;
/**
 * Payload pour un seul horaire.
 * Utilisé dans le tableau envoyé par PUT /api/employe/horaires.
 */
class HoraireDTO
{
    #[Assert\NotBlank]
    #[Assert\Positive]
    #[SerializedName('horaire_id')]
    public int $horaireId=0;

    #[SerializedName('ferme')]
    public bool $ferme = false;

    /**
     * Format attendu : "HH:MM" ou "" (vide si fermé).
     */
    #[SerializedName('heure_ouverture')]
    #[Assert\Regex(
        pattern: '/^(([01]\d|2[0-3]):[0-5]\d)?$/',
        message: 'Format d\'heure invalide (HH:MM attendu).'
    )]
    public ?string $heureOuverture = null;

    #[SerializedName('heure_fermeture')]
    #[Assert\Regex(
        pattern: '/^(([01]\d|2[0-3]):[0-5]\d)?$/',
        message: 'Format d\'heure invalide (HH:MM attendu).'
    )]
    public ?string $heureFermeture = null;
}
