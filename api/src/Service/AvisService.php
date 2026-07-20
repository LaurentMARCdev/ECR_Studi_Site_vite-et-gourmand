<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CreerAvisDTO;
use App\Entity\Avis;
use App\Entity\Utilisateur;
use App\Repository\AvisRepository;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier du domaine Avis.
 *
 * Responsabilités :
 *  - Création d'un avis avec toutes les vérifications métier
 *  - Modération (validation / refus) par employé/admin
 *
 * Règles appliquées à la création (issues du sujet) :
 *  1. La commande doit exister ET appartenir à l'utilisateur
 *  2. La commande doit être en statut TERMINEE
 *  3. Un seul avis autorisé par commande
 */
class AvisService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AvisRepository         $avis,
        private readonly CommandeRepository     $commandes,
    ) {
    }

    /**
     * Dépose un avis client.
     *
     * @throws \DomainException          si la commande n'est pas terminée
     * @throws \RuntimeException         si la commande n'appartient pas à l'utilisateur
     * @throws \InvalidArgumentException si la commande n'existe pas
     */
    public function deposerAvis(Utilisateur $utilisateur, CreerAvisDTO $dto): Avis
    {
        // 1. Récupération de la commande
        $commande = $this->commandes->findByNumero($dto->numeroCommande);
        if (!$commande) {
            throw new \InvalidArgumentException('Commande introuvable.');
        }

        // 2. Vérification d'appartenance
        if ($commande->getUtilisateur()->getUtilisateurId() !== $utilisateur->getUtilisateurId()) {
            throw new \RuntimeException('Cette commande ne vous appartient pas.');
        }

        // 3. La commande doit être terminée
        if (!$commande->getStatut()->autoriseDepotAvis()) {
            throw new \DomainException(
                'Vous ne pouvez déposer un avis qu\'une fois votre commande terminée.'
            );
        }

        // 4. Un seul avis par commande
        if ($this->avis->existeAvisPourCommande($commande)) {
            throw new \DomainException('Vous avez déjà déposé un avis pour cette commande.');
        }

        // 5. Création
        $avis = new Avis();
        $avis
            ->setUtilisateur($utilisateur)
            ->setCommande($commande)
            ->setNote($dto->note)
            ->setCommentaire($dto->commentaire);

        $this->em->persist($avis);
        $this->em->flush();

        return $avis;
    }

    /**
     * Valide un avis — il devient visible sur la page d'accueil.
     */
    public function validerAvis(Avis $avis, Utilisateur $moderateur): void
    {
        $avis->valider($moderateur);
        $this->em->flush();
    }

    /**
     * Refuse un avis — il reste en base pour la traçabilité mais n'est
     * pas affiché publiquement.
     */
    public function refuserAvis(Avis $avis, Utilisateur $moderateur): void
    {
        $avis->refuser($moderateur);
        $this->em->flush();
    }
}
