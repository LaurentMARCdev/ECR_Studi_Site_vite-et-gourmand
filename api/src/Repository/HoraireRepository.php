<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Horaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Horaire>
 */
class HoraireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Horaire::class);
    }

    /**
     * Récupère les 7 horaires ordonnés du lundi au dimanche.
     *
     * @return Horaire[]
     */
    public function findAllOrdonnes(): array
    {
        return $this->findBy([], ['ordreJour' => 'ASC']);
    }
}
