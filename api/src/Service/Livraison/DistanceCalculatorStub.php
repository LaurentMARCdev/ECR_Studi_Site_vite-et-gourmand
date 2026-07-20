<?php

declare(strict_types=1);

namespace App\Service\Livraison;

/**
 * Implémentation par défaut du calcul de distance.
 *
 * En dev/démo : utilise une table de correspondance codée en dur
 * pour les principales villes de la métropole bordelaise.
 *
 * En prod : à remplacer par un adaptateur vers l'API Adresse (data.gouv.fr)
 * ou Google Distance Matrix, injecté via un alias dans services.yaml.
 */
class DistanceCalculatorStub implements DistanceCalculatorInterface
{
    /**
     * Distance approximative en km depuis Bordeaux centre.
     * Sources : distances routières indicatives.
     */
    private const DISTANCES_CONNUES = [
        // Bordeaux Métropole — proche
        'bordeaux'          => 0.0,
        'talence'           => 4.0,
        'bègles'            => 4.5,
        'begles'            => 4.5,
        'le bouscat'        => 5.0,
        'cenon'             => 5.5,
        'lormont'           => 7.0,
        'mérignac'          => 8.0,
        'merignac'          => 8.0,
        'pessac'            => 8.5,
        'floirac'           => 6.5,
        'gradignan'         => 9.0,
        'villenave-d\'ornon' => 9.5,
        'villenave d\'ornon' => 9.5,
        'eysines'           => 10.0,
        'saint-médard-en-jalles' => 15.0,
        // Bordeaux Métropole — plus éloigné
        'ambarès-et-lagrave' => 15.5,
        'saint-aubin-de-médoc' => 18.0,
        'blanquefort'       => 13.5,
        // Hors métropole
        'arcachon'          => 60.0,
        'saint-emilion'     => 40.0,
        'libourne'          => 35.0,
        'la teste-de-buch'  => 55.0,
    ];

    public function calculerDistanceDepuisBordeaux(string $ville, string $adresseComplete): float
    {
        $normalisee = $this->normaliser($ville);

        if (isset(self::DISTANCES_CONNUES[$normalisee])) {
            return self::DISTANCES_CONNUES[$normalisee];
        }

        // Ville inconnue : on retourne une valeur par défaut raisonnable
        // (le sujet ne précise pas, on prend 20 km, moyenne métropole).
        // En prod, on interrogerait l'API et retomberait sur 0 en cas d'échec.
        return 20.0;
    }

    /**
     * Normalise une ville : minuscules, sans espaces superflus.
     */
    private function normaliser(string $ville): string
    {
        return trim(mb_strtolower($ville, 'UTF-8'));
    }
}
