<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload attendu pour créer une nouvelle commande.
 *
 * Utilisé par POST /api/commandes.
 */
class CreerCommandeDTO
{
    #[Assert\NotBlank(message: 'Le menu est obligatoire.')]
    #[Assert\Positive]
    public int $menuId = 0;

    #[Assert\NotBlank(message: 'Le nombre de personnes est obligatoire.')]
    #[Assert\Positive(message: 'Le nombre de personnes doit être positif.')]
    public int $nombrePersonnes = 0;

    /**
     * Date au format ISO 8601 (YYYY-MM-DD).
     */
    #[Assert\NotBlank(message: 'La date de prestation est obligatoire.')]
    #[Assert\Date(message: 'Format de date invalide (YYYY-MM-DD attendu).')]
    public string $datePrestation = '';

    /**
     * Heure au format HH:MM.
     */
    #[Assert\NotBlank(message: 'L\'heure de livraison est obligatoire.')]
    #[Assert\Regex(pattern: '/^([01]\d|2[0-3]):[0-5]\d$/', message: 'Format d\'heure invalide (HH:MM attendu).')]
    public string $heureLivraison = '';

    #[Assert\NotBlank(message: 'L\'adresse de livraison est obligatoire.')]
    #[Assert\Length(max: 255)]
    public string $adresseLivraison = '';

    #[Assert\NotBlank(message: 'La ville de livraison est obligatoire.')]
    #[Assert\Length(max: 100)]
    public string $villeLivraison = '';

    /**
     * Indique si du matériel de service (plats, couverts) est prêté.
     * Impacte le cycle de vie : passage par ATTENTE_MATERIEL après LIVRE.
     */
    public bool $pretMateriel = false;
}
