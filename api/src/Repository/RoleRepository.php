<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Role;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    /**
     * Récupère un rôle par son libellé (ex: 'utilisateur').
     * Retourne null si non trouvé — l'appelant décide de la stratégie
     * (throw exception, création à la volée, etc.).
     */
    public function findByLibelle(string $libelle): ?Role
    {
        return $this->findOneBy(['libelle' => $libelle]);
    }
}
