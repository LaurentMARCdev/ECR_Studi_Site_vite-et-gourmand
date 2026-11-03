<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO pour PUT /api/utilisateurs/profil
 *
 * Mapping front → back :
 *   gsm      → telephone
 *   adresse  → adressePostale
 *
 * Convention snake_case/camelCase gérée via #[SerializedName]
 * (même approche que HoraireDTO pour cohérence).
 */
class UtilisateurProfilDTO
{
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(max: 100)]
    public string $prenom = '';

    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 100)]
    public string $nom = '';

    #[Assert\NotBlank(message: 'L\'e-mail est obligatoire.')]
    #[Assert\Email(message: 'Format d\'e-mail invalide.')]
    #[Assert\Length(max: 180)]
    public string $email = '';

    /**
     * Numéro de téléphone (champ "gsm" côté front, "telephone" côté entité).
     */
    #[SerializedName('gsm')]
    #[Assert\NotBlank(message: 'Le numéro de téléphone est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^(\+?\d[\d\s\-().]{6,18}\d)$/',
        message: 'Format de numéro invalide.'
    )]
    public string $telephone = '';

    /**
     * Adresse postale (champ "adresse" côté front, "adressePostale" côté entité).
     */
    #[SerializedName('adresse')]
    #[Assert\NotBlank(message: 'L\'adresse postale est obligatoire.')]
    #[Assert\Length(max: 255)]
    public string $adressePostale = '';

    /**
     * Mot de passe actuel — requis UNIQUEMENT si on change le mdp.
     * On ne met pas NotBlank car il peut être absent (modif profil sans changer mdp).
     */
    #[SerializedName('mdpActuel')]
    public ?string $mdpActuel = null;

    /**
     * Nouveau mot de passe — optionnel.
     * Si fourni, min 10 caractères (conforme à la politique Argon2id du projet).
     */
    #[SerializedName('mdpNouveau')]
    #[Assert\Length(
        min: 10,
        minMessage: 'Le nouveau mot de passe doit faire au moins 10 caractères.'
    )]
    public ?string $mdpNouveau = null;
}