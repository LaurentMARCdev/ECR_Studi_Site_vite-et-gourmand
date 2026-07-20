<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Plat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Plat>
 */
class PlatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plat::class);
    }

    /**
     * @param int[] $ids
     * @return Plat[]
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        return $this->createQueryBuilder('p')
            ->leftJoin('p.allergenes', 'a')->addSelect('a')
            ->where('p.platId IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche par catégorie (utile pour l'admin qui construit un menu).
     * @return Plat[]
     */
    public function findByCategorie(string $categorie): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.allergenes', 'a')->addSelect('a')
            ->where('p.categorie = :cat')
            ->setParameter('cat', $categorie)
            ->orderBy('p.titre', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
