<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload pour la création d'un compte employé par l'administrateur.
 *
 * Sujet : "L'administrateur peut créer un compte employé (avec un mot de passe
 *          provisoire fourni oralement, jamais envoyé par mail)."
 *
 * Note importante : le mot de passe est bien reçu ici en clair côté back,
 * mais N'EST PAS transmis par e-mail à l'employé (uniquement une notification
 * de création). L'admin doit lui communiquer verbalement.
 */
class CreerEmployeDTO
{
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(min: 1, max: 100)]
    public string $prenom = '';

    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(min: 1, max: 100)]
    public string $nom = '';

    #[Assert\NotBlank(message: 'L\'e-mail est obligatoire.')]
    #[Assert\Email(message: 'Format d\'e-mail invalide.')]
    #[Assert\Length(max: 180)]
    public string $email = '';

    #[Assert\NotBlank(message: 'Le mot de passe provisoire est obligatoire.')]
    #[Assert\Length(min: 10, minMessage: 'Le mot de passe doit contenir au moins 10 caractères.')]
    #[Assert\Regex(pattern: '/[A-Z]/', message: 'Doit contenir une majuscule.')]
    #[Assert\Regex(pattern: '/[a-z]/', message: 'Doit contenir une minuscule.')]
    #[Assert\Regex(pattern: '/\d/',    message: 'Doit contenir un chiffre.')]
    #[Assert\Regex(pattern: '/[^A-Za-z0-9]/', message: 'Doit contenir un caractère spécial.')]
    public string $motDePasse = '';

    /**
     * Champs optionnels — remplis avec des valeurs par défaut si absents,
     * car ils sont obligatoires en base mais peuvent être complétés par
     * l'employé lui-même après connexion.
     */
    public string $telephone = '';
    public string $adressePostale = '';
}
