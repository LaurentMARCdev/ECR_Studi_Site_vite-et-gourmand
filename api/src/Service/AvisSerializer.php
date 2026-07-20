<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Avis;

/**
 * Sérialise un Avis vers le format JSON attendu par le front.
 *
 * Deux formats :
 *  - Public       : minimal, sans données perso complètes (RGPD)
 *  - Modération   : complet, avec e-mail et lien commande pour l'employé
 */
class AvisSerializer
{
    /**
     * Format public — affiché sur la page d'accueil.
     * Le nom de l'auteur est réduit à l'initiale (RGPD-friendly).
     *
     * @return array<string, mixed>
     */
    public function toArrayPublic(Avis $avis): array
    {
        return [
            'avis_id'       => $avis->getAvisId(),
            'note'          => $avis->getNote(),
            'commentaire'   => $avis->getCommentaire(),
            'auteur_prenom' => $avis->getUtilisateur()->getPrenom(),
            'auteur_nom'    => substr($avis->getUtilisateur()->getNom(), 0, 1) . '.',
            'menu_titre'    => $avis->getCommande()->getMenu()->getTitre(),
            'date'          => $avis->getDateCreation()->format('Y-m-d'),
        ];
    }

    /**
     * Format complet pour la modération employé/admin.
     * Comprend le nom complet, l'e-mail et le numéro de commande.
     *
     * @return array<string, mixed>
     */
    public function toArrayModeration(Avis $avis): array
    {
        return [
            'avis_id'         => $avis->getAvisId(),
            'note'            => $avis->getNote(),
            'commentaire'     => $avis->getCommentaire(),
            'statut'          => $avis->getStatut()->value,
            'statut_label'    => $avis->getStatut()->label(),
            'auteur_prenom'   => $avis->getUtilisateur()->getPrenom(),
            'auteur_nom'      => $avis->getUtilisateur()->getNom(),
            'auteur_email'    => $avis->getUtilisateur()->getEmail(),
            'menu_titre'      => $avis->getCommande()->getMenu()->getTitre(),
            'numero_commande' => $avis->getCommande()->getNumeroCommande(),
            'date'            => $avis->getDateCreation()->format(\DateTimeInterface::ATOM),
            'date_moderation' => $avis->getDateModeration()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Format pour l'espace utilisateur (voir ses propres avis).
     *
     * @return array<string, mixed>
     */
    public function toArrayUtilisateur(Avis $avis): array
    {
        return [
            'avis_id'         => $avis->getAvisId(),
            'note'            => $avis->getNote(),
            'commentaire'     => $avis->getCommentaire(),
            'statut'          => $avis->getStatut()->value,
            'statut_label'    => $avis->getStatut()->label(),
            'menu_titre'      => $avis->getCommande()->getMenu()->getTitre(),
            'numero_commande' => $avis->getCommande()->getNumeroCommande(),
            'date'            => $avis->getDateCreation()->format('Y-m-d'),
        ];
    }
}
