<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    public function findByEmail(string $email): ?Utilisateur
    {
        return $this->findOneBy(['email' => strtolower(trim($email))]);
    }

    public function findActifByEmail(string $email): ?Utilisateur
    {
        return $this->findOneBy([
            'email' => strtolower(trim($email)),
            'actif' => true,
        ]);
    }

    /**
     * Cherche un utilisateur ayant un token de réinitialisation valide.
     * Le token en base est haché avec SHA-256 (le token en clair
     * n'existe que dans l'e-mail envoyé au user).
     */
    public function findByValidResetToken(string $tokenClair): ?Utilisateur
    {
        $tokenHash = hash('sha256', $tokenClair);

        return $this->createQueryBuilder('u')
            ->where('u.resetToken = :hash')
            ->andWhere('u.resetTokenExpiresAt > :now')
            ->andWhere('u.actif = true')
            ->setParameter('hash', $tokenHash)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Utilisé par Symfony pour re-hacher automatiquement les mots de passe
     * quand l'algo est mis à jour (rehash transparent).
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Utilisateur) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
}
