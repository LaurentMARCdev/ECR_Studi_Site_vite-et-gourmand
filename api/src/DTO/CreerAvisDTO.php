<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload pour le dépôt d'un avis client depuis son espace personnel.
 * POST /api/avis
 */
class CreerAvisDTO
{
    /**
     * Numéro de la commande concernée (format VG-YYYY-NNNN).
     * Utilisé plutôt que l'ID interne car c'est l'identifiant visible côté client.
     */
    #[Assert\NotBlank(message: 'Le numéro de commande est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^VG-\d{4}-\d{4}$/',
        message: 'Format de numéro de commande invalide.'
    )]
    public string $numeroCommande = '';

    #[Assert\NotBlank(message: 'La note est obligatoire.')]
    #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'La note doit être comprise entre {{ min }} et {{ max }}.')]
    public int $note = 0;

    #[Assert\NotBlank(message: 'Le commentaire est obligatoire.')]
    #[Assert\Length(
        min: 10,
        max: 2000,
        minMessage: 'Le commentaire doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le commentaire ne peut pas dépasser {{ limit }} caractères.',
    )]
    public string $commentaire = '';
}
