<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ReinitialiserMdpDTO
{
    #[Assert\NotBlank(message: 'Token manquant.')]
    public string $token = '';

    #[Assert\NotBlank(message: 'Le nouveau mot de passe est obligatoire.')]
    #[Assert\Length(min: 10, minMessage: 'Le mot de passe doit contenir au moins 10 caractères.')]
    #[Assert\Regex(pattern: '/[A-Z]/', message: 'Doit contenir une majuscule.')]
    #[Assert\Regex(pattern: '/[a-z]/', message: 'Doit contenir une minuscule.')]
    #[Assert\Regex(pattern: '/\d/',    message: 'Doit contenir un chiffre.')]
    #[Assert\Regex(pattern: '/[^A-Za-z0-9]/', message: 'Doit contenir un caractère spécial.')]
    public string $nouveauMotDePasse = '';
}
