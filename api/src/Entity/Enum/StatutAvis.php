<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Statut de modération d'un avis client.
 *
 * Flux : EN_ATTENTE → VALIDE  (publication sur la page d'accueil)
 *                   ↘ REFUSE  (rejeté, non visible)
 *
 * Le sujet exige explicitement que seuls les avis validés soient publiés.
 */
enum StatutAvis: string
{
    case EN_ATTENTE = 'en_attente';
    case VALIDE     = 'valide';
    case REFUSE     = 'refuse';

    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE => 'En attente de modération',
            self::VALIDE     => 'Validé',
            self::REFUSE     => 'Refusé',
        };
    }

    public function estPublic(): bool
    {
        return $this === self::VALIDE;
    }
}
