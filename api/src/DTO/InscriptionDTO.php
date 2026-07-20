<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO validé pour l'inscription d'un nouvel utilisateur.
 *
 * Toutes les règles métier du cahier des charges sont ici :
 *  - E-mail unique et valide
 *  - Mot de passe : 10 caractères min, 1 majuscule, 1 minuscule, 1 chiffre, 1 spécial
 *  - Nom, prénom, téléphone, adresse obligatoires
 */
class InscriptionDTO
{
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(min: 1, max: 100)]
    public string $prenom = '';

    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(min: 1, max: 100)]
    public string $nom = '';

    #[Assert\NotBlank(message: 'L\'adresse e-mail est obligatoire.')]
    #[Assert\Email(message: 'Format d\'e-mail invalide.')]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\NotBlank(message: 'Le numéro de téléphone est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^(\+?\d[\d\s\-().]{6,18}\d)$/',
        message: 'Format de numéro invalide.'
    )]
    public string $gsm = '';

    #[Assert\NotBlank(message: 'L\'adresse postale est obligatoire.')]
    #[Assert\Length(max: 255)]
    public string $adresse = '';

    /**
     * Règle de sécurité du cahier des charges :
     * 10 caractères minimum, avec au minimum
     *  - 1 caractère spécial
     *  - 1 majuscule
     *  - 1 minuscule
     *  - 1 chiffre
     */
    #[Assert\NotBlank(message: 'Le mot de passe est obligatoire.')]
    #[Assert\Length(
        min: 10,
        max: 4096,
        minMessage: 'Le mot de passe doit contenir au moins 10 caractères.'
    )]
    #[Assert\Regex(
        pattern: '/[A-Z]/',
        message: 'Le mot de passe doit contenir au moins une majuscule.'
    )]
    #[Assert\Regex(
        pattern: '/[a-z]/',
        message: 'Le mot de passe doit contenir au moins une minuscule.'
    )]
    #[Assert\Regex(
        pattern: '/\d/',
        message: 'Le mot de passe doit contenir au moins un chiffre.'
    )]
    #[Assert\Regex(
        pattern: '/[^A-Za-z0-9]/',
        message: 'Le mot de passe doit contenir au moins un caractère spécial.'
    )]
    public string $motDePasse = '';
}
