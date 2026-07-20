<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\AnnulerCommandeEmployeDTO;
use App\DTO\CreerCommandeDTO;
use App\DTO\ModifierCommandeDTO;
use App\Entity\Commande;
use App\Entity\Enum\StatutCommande;
use App\Entity\Menu;
use App\Entity\Utilisateur;
use App\Repository\CommandeRepository;
use App\Repository\MenuRepository;
use App\Service\Livraison\DistanceCalculatorInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Logique métier du domaine Commandes.
 *
 * Responsabilités :
 *  - Créer une commande (avec calcul du prix, décrémentation stock, notification)
 *  - Modifier / annuler par l'utilisateur
 *  - Transitionner les statuts (employé)
 *  - Annuler par l'employé (avec motif + mode de contact obligatoires)
 *  - Envoyer les notifications e-mail aux étapes clés
 *
 * Toute opération de mutation utilise une transaction Doctrine pour garantir
 * la cohérence (stock + commande + historique doivent être tous validés ou tous rollback).
 */
class CommandeService
{
    public function __construct(
        private readonly EntityManagerInterface        $em,
        private readonly CommandeRepository            $commandes,
        private readonly MenuRepository                $menus,
        private readonly DistanceCalculatorInterface   $distanceCalculator,
        private readonly MailerService                 $mailer,
        private readonly LoggerInterface               $logger,
    ) {
    }

    // ═══════════════════════════════════════════════════════════
    // CRÉATION
    // ═══════════════════════════════════════════════════════════

    /**
     * Crée une nouvelle commande pour un utilisateur.
     *
     * Actions en transaction :
     *  1. Verrouille le menu (pour éviter la course sur le stock)
     *  2. Vérifie la disponibilité et le stock
     *  3. Calcule la distance et les prix
     *  4. Génère le numéro de commande unique
     *  5. Décrémente le stock
     *  6. Persiste + envoie l'e-mail de confirmation
     *
     * @throws \DomainException si menu épuisé, personnes < min, etc.
     */
    public function creer(Utilisateur $utilisateur, CreerCommandeDTO $dto): Commande
    {
        $this->em->beginTransaction();

        try {
            // 1. Charger le menu avec verrou pessimiste sur la ligne
            //    pour éviter que 2 clients simultanés commandent le dernier stock.
            $menu = $this->menus->find($dto->menuId, LockMode::PESSIMISTIC_WRITE);
            if (!$menu instanceof Menu) {
                throw new \DomainException('Menu introuvable.');
            }

            // 2. Vérification disponibilité
            if (!$menu->estDisponible()) {
                throw new \DomainException('Ce menu n\'est plus disponible à la commande.');
            }

            // 3. Vérification nombre de personnes minimum
            if ($dto->nombrePersonnes < $menu->getNombrePersonneMinimum()) {
                throw new \DomainException(sprintf(
                    'Ce menu nécessite un minimum de %d personnes.',
                    $menu->getNombrePersonneMinimum()
                ));
            }

            // 4. Calcul de la distance (0 si Bordeaux)
            $distance = $this->distanceCalculator->calculerDistanceDepuisBordeaux(
                $dto->villeLivraison,
                $dto->adresseLivraison
            );

            // 5. Construction de la commande
            $commande = new Commande();
            $commande
                ->setUtilisateur($utilisateur)
                ->setMenu($menu)
                ->setNombrePersonnes($dto->nombrePersonnes)
                ->setDatePrestation(new \DateTimeImmutable($dto->datePrestation))
                ->setHeureLivraison(new \DateTimeImmutable($dto->heureLivraison))
                ->setAdresseLivraison($dto->adresseLivraison)
                ->setVilleLivraison($dto->villeLivraison)
                ->setDistanceKm($distance)
                ->setPretMateriel($dto->pretMateriel)
                ->setStatutInitial(StatutCommande::EN_ATTENTE);

            // 6. Calcul du prix (réduction, livraison, total)
            $commande->recalculerPrix();

            // 7. Génération du numéro unique
            $commande->setNumeroCommande($this->genererNumeroUnique());

            // 8. Décrémentation du stock (pas d'effet si stock illimité)
            $menu->decrementerStock();

            $this->em->persist($commande);
            $this->em->flush();
            $this->em->commit();

        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        // 9. E-mail de confirmation (hors transaction — un échec de mail
        //    ne doit pas annuler la commande).
        try {
            $this->mailer->envoyerConfirmationCommande($commande);
        } catch (\Throwable $e) {
            $this->logger->error('Échec envoi mail confirmation commande', [
                'numero'    => $commande->getNumeroCommande(),
                'exception' => $e->getMessage(),
            ]);
        }

        return $commande;
    }

    // ═══════════════════════════════════════════════════════════
    // MODIFICATION (par l'utilisateur)
    // ═══════════════════════════════════════════════════════════

    /**
     * Modifie une commande existante par son utilisateur.
     *
     * Règle du sujet : "tout est modifiable, sauf, le choix du menu"
     * Autorisée uniquement si statut = EN_ATTENTE.
     *
     * @throws \DomainException si la commande ne peut plus être modifiée
     * @throws \RuntimeException si la commande n'appartient pas à l'utilisateur
     */
    public function modifierParUtilisateur(
        Commande $commande,
        Utilisateur $utilisateur,
        ModifierCommandeDTO $dto,
    ): Commande {
        // Vérification d'appartenance
        $this->assertProprietaire($commande, $utilisateur);

        // Vérification du statut
        if (!$commande->getStatut()->autoriseModificationUtilisateur()) {
            throw new \DomainException(
                'Cette commande ne peut plus être modifiée (statut : ' . $commande->getStatut()->label() . ').'
            );
        }

        // Mise à jour des données
        $distance = $this->distanceCalculator->calculerDistanceDepuisBordeaux(
            $dto->villeLivraison,
            $dto->adresseLivraison
        );

        $commande
            ->setNombrePersonnes($dto->nombrePersonnes)
            ->setDatePrestation(new \DateTimeImmutable($dto->datePrestation))
            ->setHeureLivraison(new \DateTimeImmutable($dto->heureLivraison))
            ->setAdresseLivraison($dto->adresseLivraison)
            ->setVilleLivraison($dto->villeLivraison)
            ->setDistanceKm($distance);

        if ($dto->pretMateriel !== null) {
            $commande->setPretMateriel($dto->pretMateriel);
        }

        // Recalcul complet des prix
        $commande->recalculerPrix();

        $this->em->flush();

        return $commande;
    }

    // ═══════════════════════════════════════════════════════════
    // ANNULATION
    // ═══════════════════════════════════════════════════════════

    /**
     * L'utilisateur annule sa propre commande.
     * Autorisée uniquement si statut = EN_ATTENTE (avant acceptation).
     */
    public function annulerParUtilisateur(Commande $commande, Utilisateur $utilisateur): void
    {
        $this->assertProprietaire($commande, $utilisateur);

        if (!$commande->getStatut()->autoriseModificationUtilisateur()) {
            throw new \DomainException(
                'Cette commande ne peut plus être annulée directement. Contactez-nous.'
            );
        }

        $this->em->beginTransaction();
        try {
            $commande->annulerParUtilisateur();
            $commande->getMenu()->incrementerStock();
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        $this->notifierChangementSiPertinent($commande);
    }

    /**
     * L'employé annule une commande — mode de contact et motif obligatoires.
     */
    public function annulerParEmploye(Commande $commande, AnnulerCommandeEmployeDTO $dto): void
    {
        // La règle métier "avoir contacté le client" est validée côté UI :
        // le back exige juste les infos obligatoires (mode + motif).
        $this->em->beginTransaction();
        try {
            $commande->annuler($dto->modeContact, $dto->motif);
            $commande->getMenu()->incrementerStock();
            $this->em->flush();
            $this->em->commit();
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        $this->notifierChangementSiPertinent($commande);
    }

    // ═══════════════════════════════════════════════════════════
    // TRANSITION DE STATUT (employé/admin)
    // ═══════════════════════════════════════════════════════════

    /**
     * Transitionne le statut d'une commande vers un nouveau statut.
     *
     * Règles particulières :
     *  - Après LIVRE, si prêt de matériel → forcer ATTENTE_MATERIEL
     *    plutôt que TERMINEE directement (sinon on manque une notification).
     *  - Après ATTENTE_MATERIEL → TERMINEE (marque la restitution).
     *
     * @throws \DomainException si transition non autorisée
     */
    public function transitionnerStatut(Commande $commande, StatutCommande $nouveauStatut): void
    {
        // Si passage à TERMINEE alors qu'un matériel est prêté sans être encore
        // marqué comme restitué, on refuse pour rappeler l'étape ATTENTE_MATERIEL.
        if ($nouveauStatut === StatutCommande::TERMINEE
            && $commande->getStatut() === StatutCommande::LIVRE
            && $commande->isPretMateriel()
            && !$commande->isRestitutionMateriel()) {
            throw new \DomainException(
                'Cette commande implique un prêt de matériel : elle doit d\'abord passer par "Attente retour matériel".'
            );
        }

        // Passage à TERMINEE depuis ATTENTE_MATERIEL → on marque la restitution.
        if ($nouveauStatut === StatutCommande::TERMINEE
            && $commande->getStatut() === StatutCommande::ATTENTE_MATERIEL) {
            $commande->setRestitutionMateriel(true);
        }

        $commande->changerStatut($nouveauStatut);
        $this->em->flush();

        $this->notifierChangementSiPertinent($commande);
    }

    // ═══════════════════════════════════════════════════════════
    // NOTIFICATION E-MAIL
    // ═══════════════════════════════════════════════════════════

    /**
     * Envoie l'e-mail approprié selon le nouveau statut de la commande.
     */
    private function notifierChangementSiPertinent(Commande $commande): void
    {
        if (!$commande->getStatut()->declencheNotificationClient()) {
            return;
        }

        try {
            match ($commande->getStatut()) {
                StatutCommande::ATTENTE_MATERIEL => $this->mailer->envoyerNotificationRetourMateriel($commande),
                StatutCommande::TERMINEE         => $this->mailer->envoyerInvitationAvis($commande),
                default                          => $this->mailer->envoyerChangementStatutCommande($commande),
            };
        } catch (\Throwable $e) {
            $this->logger->error('Échec envoi mail changement statut', [
                'numero'    => $commande->getNumeroCommande(),
                'statut'    => $commande->getStatut()->value,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // UTILITAIRES
    // ═══════════════════════════════════════════════════════════

    private function assertProprietaire(Commande $commande, Utilisateur $utilisateur): void
    {
        if ($commande->getUtilisateur()->getUtilisateurId() !== $utilisateur->getUtilisateurId()) {
            throw new \RuntimeException('Cette commande ne vous appartient pas.');
        }
    }

    /**
     * Génère un numéro de commande unique au format VG-YYYY-NNNN.
     *
     * Approche simple pour l'ECF : on compte les commandes de l'année et on
     * incrémente. Le verrou pessimiste sur le menu (à la création) + la
     * contrainte UNIQUE sur numero_commande protègent contre les doublons.
     *
     * Pour un vrai environnement production, on utiliserait une séquence
     * PostgreSQL dédiée (CREATE SEQUENCE commande_seq_2025 ...).
     */
    private function genererNumeroUnique(): string
    {
        $annee = (int)date('Y');
        $tentatives = 0;

        do {
            $sequentiel = $this->commandes->countCommandesAnnee($annee) + 1 + $tentatives;
            $numero = Commande::generateNumero($sequentiel, $annee);
            $existe = $this->commandes->findOneBy(['numeroCommande' => $numero]) !== null;
            $tentatives++;
        } while ($existe && $tentatives < 100);

        if ($tentatives >= 100) {
            throw new \RuntimeException('Impossible de générer un numéro de commande unique.');
        }

        return $numero;
    }
}
