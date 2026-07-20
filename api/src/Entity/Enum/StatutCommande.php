<?php

declare(strict_types=1);

namespace App\Entity\Enum;

/**
 * Cycle de vie d'une commande.
 *
 * Le sujet définit 8 statuts avec un flux strict :
 *   en_attente ─→ accepte ─→ en_preparation ─→ en_cours_livraison ─→ livre
 *                                                                     │
 *                                                    ┌────────────────┤
 *                                                    ↓                ↓
 *                                            attente_materiel   terminee
 *                                                    │
 *                                                    ↓
 *                                                terminee
 *
 *   annulee : accessible depuis en_attente, accepte, en_preparation (règles métier).
 *
 * En pratique, chaque transition est validée via `transitionsAutorisees()`.
 */
enum StatutCommande: string
{
    case EN_ATTENTE           = 'en_attente';
    case ACCEPTE              = 'accepte';
    case EN_PREPARATION       = 'en_preparation';
    case EN_COURS_LIVRAISON   = 'en_cours_livraison';
    case LIVRE                = 'livre';
    case ATTENTE_MATERIEL     = 'attente_materiel';
    case TERMINEE             = 'terminee';
    case ANNULEE              = 'annulee';

    /**
     * Libellé lisible pour affichage front / mails.
     */
    public function label(): string
    {
        return match ($this) {
            self::EN_ATTENTE          => 'En attente',
            self::ACCEPTE             => 'Acceptée',
            self::EN_PREPARATION      => 'En préparation',
            self::EN_COURS_LIVRAISON  => 'En cours de livraison',
            self::LIVRE               => 'Livrée',
            self::ATTENTE_MATERIEL    => 'En attente du retour de matériel',
            self::TERMINEE            => 'Terminée',
            self::ANNULEE             => 'Annulée',
        };
    }

    /**
     * Retourne la liste des statuts atteignables depuis le statut actuel.
     * Utilisé pour valider les transitions et pour peupler le dropdown côté front.
     *
     * @return StatutCommande[]
     */
    public function transitionsAutorisees(): array
    {
        return match ($this) {
            self::EN_ATTENTE          => [self::ACCEPTE, self::ANNULEE],
            self::ACCEPTE             => [self::EN_PREPARATION, self::ANNULEE],
            self::EN_PREPARATION      => [self::EN_COURS_LIVRAISON, self::ANNULEE],
            self::EN_COURS_LIVRAISON  => [self::LIVRE],
            self::LIVRE               => [self::ATTENTE_MATERIEL, self::TERMINEE],
            self::ATTENTE_MATERIEL    => [self::TERMINEE],
            self::TERMINEE, self::ANNULEE => [], // Statuts finaux
        };
    }

    /**
     * L'utilisateur peut-il encore modifier / annuler sa commande à ce statut ?
     * D'après le sujet : "L'annulation de commande est possible, tant qu'un
     * employé n'a pas passé la commande en 'accepté'".
     */
    public function autoriseModificationUtilisateur(): bool
    {
        return $this === self::EN_ATTENTE;
    }

    /**
     * Statut auquel l'utilisateur peut déposer un avis (une fois la commande terminée).
     */
    public function autoriseDepotAvis(): bool
    {
        return $this === self::TERMINEE;
    }

    /**
     * Statuts pour lesquels un mail doit être envoyé au client lors du changement.
     * (Pas la peine de spammer à chaque micro-changement.)
     */
    public function declencheNotificationClient(): bool
    {
        return match ($this) {
            self::ACCEPTE, self::ATTENTE_MATERIEL, self::TERMINEE, self::ANNULEE => true,
            default => false,
        };
    }
}
