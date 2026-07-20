<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\InscriptionDTO;
use App\Entity\Role;
use App\Entity\Utilisateur;
use App\Repository\RoleRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Contient toute la logique métier liée à l'authentification :
 *  - Inscription (avec attribution du rôle 'utilisateur' par défaut)
 *  - Réinitialisation du mot de passe (génération token, hachage, expiration)
 *  - Changement du mot de passe
 *
 * Ce service reste indépendant de HTTP : il est réutilisable
 * depuis un contrôleur, une commande CLI, un test unitaire, etc.
 */
class AuthService
{
    /**
     * Durée de validité du token de réinitialisation.
     */
    public const RESET_TOKEN_TTL_SECONDS = 3600; // 1 h

    public function __construct(
        private readonly EntityManagerInterface       $em,
        private readonly UtilisateurRepository        $utilisateurs,
        private readonly RoleRepository               $roles,
        private readonly UserPasswordHasherInterface  $hasher,
        private readonly MailerService                $mailer,
        private readonly LoggerInterface              $logger,
    ) {
    }

    /**
     * Inscrit un nouvel utilisateur avec le rôle "utilisateur".
     *
     * @throws \DomainException si l'e-mail est déjà utilisé
     * @throws \RuntimeException si le rôle "utilisateur" n'existe pas en BDD
     */
    public function inscrire(InscriptionDTO $dto): Utilisateur
    {
        // Vérification unicité de l'e-mail
        if ($this->utilisateurs->findByEmail($dto->email)) {
            throw new \DomainException('Cette adresse e-mail est déjà utilisée.');
        }

        $roleUtilisateur = $this->roles->findByLibelle(Role::UTILISATEUR);
        if (!$roleUtilisateur) {
            throw new \RuntimeException('Rôle "utilisateur" absent en base — exécutez les fixtures/migrations.');
        }

        $user = new Utilisateur();
        $user
            ->setPrenom($dto->prenom)
            ->setNom($dto->nom)
            ->setEmail($dto->email)
            ->setTelephone($dto->gsm)
            ->setAdressePostale($dto->adresse)
            ->setRole($roleUtilisateur)
            ->setActif(true);

        // Hachage du mot de passe (Argon2id via le hasher configuré)
        $user->setPassword($this->hasher->hashPassword($user, $dto->motDePasse));

        $this->em->persist($user);
        $this->em->flush();

        // E-mail de bienvenue (envoi asynchrone recommandé en prod via Messenger)
        try {
            $this->mailer->envoyerBienvenue($user);
        } catch (\Throwable $e) {
            // Un échec d'envoi de mail ne doit pas casser l'inscription
            $this->logger->error('Échec envoi email bienvenue', [
                'utilisateur_id' => $user->getUtilisateurId(),
                'exception'      => $e->getMessage(),
            ]);
        }

        return $user;
    }

    /**
     * Génère un token de réinitialisation, l'e-mail le lien à l'utilisateur.
     *
     * SÉCURITÉ IMPORTANTE :
     *  - Cette méthode ne révèle JAMAIS si l'e-mail existe ou non
     *    (protection contre l'énumération de comptes).
     *  - Le token en clair n'existe qu'en mémoire et dans l'e-mail envoyé.
     *  - Seul le hash SHA-256 du token est stocké en base.
     */
    public function demanderReinitialisationMdp(string $email): void
    {
        $user = $this->utilisateurs->findActifByEmail($email);

        // Compte inexistant / désactivé → on ne fait rien, mais on ne le dit pas
        if (!$user) {
            $this->logger->info('Reset password demandé pour email inconnu', ['email' => $email]);
            return;
        }

        // Génération d'un token cryptographiquement sûr (256 bits)
        $tokenClair = bin2hex(random_bytes(32));
        $tokenHash  = hash('sha256', $tokenClair);

        $user
            ->setResetToken($tokenHash)
            ->setResetTokenExpiresAt(
                (new \DateTimeImmutable())->modify('+' . self::RESET_TOKEN_TTL_SECONDS . ' seconds')
            );

        $this->em->flush();

        // Envoi de l'e-mail avec le token EN CLAIR (unique fois)
        try {
            $this->mailer->envoyerReinitialisationMotDePasse($user, $tokenClair);
        } catch (\Throwable $e) {
            $this->logger->error('Échec envoi email reset password', [
                'utilisateur_id' => $user->getUtilisateurId(),
                'exception'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Applique un nouveau mot de passe grâce au token reçu par mail.
     *
     * @throws \DomainException si le token est invalide ou expiré
     */
    public function reinitialiserMdp(string $tokenClair, string $nouveauMotDePasse): void
    {
        $user = $this->utilisateurs->findByValidResetToken($tokenClair);
        if (!$user) {
            throw new \DomainException('Lien invalide ou expiré. Veuillez refaire une demande.');
        }

        $user->setPassword($this->hasher->hashPassword($user, $nouveauMotDePasse));
        // Invalidation du token après usage (usage unique)
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);

        $this->em->flush();
    }

    /**
     * Change le mot de passe d'un utilisateur connecté (nécessite le mdp actuel).
     *
     * @throws \DomainException si le mot de passe actuel est incorrect
     */
    public function changerMotDePasse(Utilisateur $user, string $motDePasseActuel, string $nouveauMotDePasse): void
    {
        if (!$this->hasher->isPasswordValid($user, $motDePasseActuel)) {
            throw new \DomainException('Mot de passe actuel incorrect.');
        }

        $user->setPassword($this->hasher->hashPassword($user, $nouveauMotDePasse));
        $this->em->flush();
    }
}
