<?php

declare(strict_types=1);

namespace App\DTO;

use App\Entity\Enum\StatutCommande;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * PUT /api/employe/commandes/{numero}/statut
 */
class ChangerStatutDTO
{
    #[Assert\NotBlank(message: 'Le statut est obligatoire.')]
    public string $statut = '';

    /**
     * Convertit la string en enum StatutCommande, en levant une exception
     * claire si la valeur n'est pas connue.
     */
    public function toStatutCommande(): StatutCommande
    {
        return StatutCommande::tryFrom($this->statut)
            ?? throw new \InvalidArgumentException(sprintf('Statut invalide : "%s"', $this->statut));
    }
}
