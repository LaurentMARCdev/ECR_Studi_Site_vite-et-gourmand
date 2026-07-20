<?php

declare(strict_types=1);

namespace App\Service\Livraison;

/**
 * Contrat pour calculer la distance de livraison depuis Bordeaux.
 *
 * Implémentations possibles :
 *   - DistanceCalculatorStub          : renvoie une valeur codée en dur (dev)
 *   - DistanceCalculatorGouvApi       : utilise api-adresse.data.gouv.fr (prod)
 *   - DistanceCalculatorGoogleMaps    : utilise Google Distance Matrix (prod)
 *
 * On peut ainsi changer d'implémentation sans toucher au CommandeService.
 */
interface DistanceCalculatorInterface
{
    /**
     * Retourne la distance en km entre Bordeaux et l'adresse fournie.
     * Retourne 0 si la ville est Bordeaux ou si le calcul échoue
     * (fail-safe : ne pas bloquer une commande pour une erreur de géocodage).
     */
    public function calculerDistanceDepuisBordeaux(string $ville, string $adresseComplete): float;
}
