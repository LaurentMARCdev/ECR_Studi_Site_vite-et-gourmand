<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Theme;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Theme>
 */
class ThemeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Theme::class);
    }

    public function findByLibelle(string $libelle): ?Theme
    {
        return $this->findOneBy(['libelle' => $libelle]);
    }

    /** @param int[] $ids @return Theme[] */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) return [];
        return $this->findBy(['themeId' => $ids]);
    }
}
