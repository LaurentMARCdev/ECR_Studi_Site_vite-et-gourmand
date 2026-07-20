<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CreerEmployeDTO;
use App\Entity\Role;
use App\Entity\Utilisateur;
use App\Repository\RoleRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Logique métier réservée à l'administrateur.
 *
 * - Création d'employés (avec envoi d'un e-mail de notification SANS le mdp)
 * - Activation / désactivation d'un compte (soft delete)
 *
 * Règles importantes du sujet :
 *  - Le compte administrateur ne peut PAS être créé via l'interface
 *    (uniquement via seed/CLI). Cette contrainte est appliquée en refusant
 *    tout autre rôle que 'employe' dans creerEmploye().
 *  - Le mot de passe n'est JAMAIS envoyé par e-mail.
 */
class AdminService
{
    public function __construct(
        private readonly EntityManagerInterface      $em,
        private readonly UtilisateurRepository       $utilisateurs,
        private readonly RoleRepository              $roles,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly MailerService               $mailer,
        private readonly LoggerInterface             $logger,
    ) {
    }

    /**
     * Crée un nouveau compte employé.
     *
     * @throws \DomainException  si l'e-mail existe déjà
     * @throws \RuntimeException si le rôle "employe" est absent
     */
    public function creerEmploye(CreerEmployeDTO $dto): Utilisateur
    {
        if ($this->utilisateurs->findByEmail($dto->email)) {
            throw new \DomainException('Cette adresse e-mail est déjà utilisée.');
        }

        $roleEmploye = $this->roles->findByLibelle(Role::EMPLOYE);
        if (!$roleEmploye) {
            throw new \RuntimeException('Rôle "employe" absent en base — exécutez les migrations.');
        }

        $employe = new Utilisateur();
        $employe
            ->setPrenom($dto->prenom)
            ->setNom($dto->nom)
            ->setEmail($dto->email)
            ->setTelephone($dto->telephone ?: 'À compléter')
            ->setAdressePostale($dto->adressePostale ?: 'À compléter')
            ->setRole($roleEmploye)
            ->setActif(true);

        $employe->setPassword($this->hasher->hashPassword($employe, $dto->motDePasse));

        $this->em->persist($employe);
        $this->em->flush();

        // Notification e-mail — sans le mot de passe (règle de sécurité du sujet)
        try {
            $this->mailer->envoyerNotificationCompteEmploye($employe);
        } catch (\Throwable $e) {
            // Une panne d'e-mail ne doit pas bloquer la création du compte
            $this->logger->error('Échec envoi email création compte employé', [
                'utilisateur_id' => $employe->getUtilisateurId(),
                'exception'      => $e->getMessage(),
            ]);
        }

        return $employe;
    }

    /**
     * Active ou désactive un compte employé.
     *
     * Soft delete : préserve l'historique des actions de l'employé
     * (menus créés, commandes traitées, etc.).
     *
     * @throws \DomainException si l'utilisateur n'est pas un employé
     *                          (empêche la désactivation accidentelle d'un admin)
     */
    public function toggleActifEmploye(Utilisateur $employe): void
    {
        if ($employe->getRole()->getLibelle() !== Role::EMPLOYE) {
            throw new \DomainException(
                'Seuls les comptes employés peuvent être activés/désactivés depuis cette interface.'
            );
        }

        $employe->setActif(!$employe->isActif());
        $this->em->flush();
    }

    /**
     * Liste tous les employés (rôle = 'employe').
     *
     * @return Utilisateur[]
     */
    public function listerEmployes(): array
    {
        return $this->utilisateurs->createQueryBuilder('u')
            ->join('u.role', 'r')
            ->where('r.libelle = :libelle')
            ->setParameter('libelle', Role::EMPLOYE)
            ->orderBy('u.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
