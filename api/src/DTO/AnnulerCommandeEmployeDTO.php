<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload pour l'annulation d'une commande par un employé.
 *
 * Sujet : "il ne peut pas modifier / annuler les commandes avant d'avoir
 *         contacté le client par appel GSM ou mail. (Il devra mettre
 *         un motif d'annulation en spécifiant le mode de contact ainsi
 *         que le motif)"
 */
class AnnulerCommandeEmployeDTO
{
    #[Assert\NotBlank(message: 'Le mode de contact est obligatoire.')]
    #[Assert\Choice(choices: ['gsm', 'mail'], message: 'Mode de contact invalide (gsm ou mail attendu).')]
    public string $modeContact = '';

    #[Assert\NotBlank(message: 'Le motif d\'annulation est obligatoire.')]
    #[Assert\Length(min: 5, minMessage: 'Le motif doit contenir au moins 5 caractères.')]
    public string $motif = '';
}
