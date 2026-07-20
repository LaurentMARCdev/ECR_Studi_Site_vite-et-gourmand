<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Allergene;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Allergene>
 */
class AllergeneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Allergene::class);
    }

    public function findByLibelle(string $libelle): ?Allergene
    {
        return $this->findOneBy(['libelle' => $libelle]);
    }

    /** @param int[] $ids @return Allergene[] */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) return [];
        return $this->findBy(['allergeneId' => $ids]);
    }
}
