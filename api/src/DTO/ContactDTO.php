<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload du formulaire de contact public.
 * POST /api/contact — page contact.html du front.
 *
 * Les 3 champs sont exactement ceux du cahier des charges :
 *   "titre du message, email, description"
 */
class ContactDTO
{
    #[Assert\NotBlank(message: 'L\'objet du message est obligatoire.')]
    #[Assert\Length(
        min: 5,
        max: 200,
        minMessage: 'L\'objet doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'L\'objet ne peut pas dépasser {{ limit }} caractères.',
    )]
    public string $titre = '';

    #[Assert\NotBlank(message: 'L\'adresse e-mail est obligatoire.')]
    #[Assert\Email(message: 'Format d\'e-mail invalide.')]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\NotBlank(message: 'Le message est obligatoire.')]
    #[Assert\Length(
        min: 20,
        max: 2000,
        minMessage: 'Le message doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le message ne peut pas dépasser {{ limit }} caractères.',
    )]
    public string $description = '';
}
