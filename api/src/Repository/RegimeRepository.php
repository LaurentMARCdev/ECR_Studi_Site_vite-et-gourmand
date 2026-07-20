<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Regime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Regime>
 */
class RegimeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Regime::class);
    }

    public function findByLibelle(string $libelle): ?Regime
    {
        return $this->findOneBy(['libelle' => $libelle]);
    }

    /** @param int[] $ids @return Regime[] */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) return [];
        return $this->findBy(['regimeId' => $ids]);
    }
}
