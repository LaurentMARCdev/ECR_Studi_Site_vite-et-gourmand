<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Horaire;

/**
 * Sérialise un Horaire vers le format attendu par le front.
 *
 * Format cible :
 * {
 *   horaire_id      : 1,
 *   jour            : "Lundi",
 *   heure_ouverture : "08:00",   // string ou null
 *   heure_fermeture : "19:00",   // string ou null
 *   ferme           : false
 * }
 */
class HoraireSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Horaire $h): array
    {
        return [
            'horaire_id'      => $h->getHoraireId(),
            'jour'            => $h->getJour(),
            'ordre_jour'      => $h->getOrdreJour(),
            'heure_ouverture' => $h->getHeureOuverture()?->format('H:i'),
            'heure_fermeture' => $h->getHeureFermeture()?->format('H:i'),
            'ferme'           => $h->isFerme(),
        ];
    }
}
