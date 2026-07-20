<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Commande;
use App\Entity\Enum\StatutCommande;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    public function findByNumero(string $numero): ?Commande
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.menu',              'm')->addSelect('m')
            ->leftJoin('c.utilisateur',       'u')->addSelect('u')
            ->leftJoin('c.historiqueStatuts', 'h')->addSelect('h')
            ->where('c.numeroCommande = :num')
            ->setParameter('num', $numero)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Commande[]
     */
    public function findByUtilisateur(Utilisateur $u): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.menu',              'm')->addSelect('m')
            ->leftJoin('c.historiqueStatuts', 'h')->addSelect('h')
            ->where('c.utilisateur = :u')
            ->setParameter('u', $u)
            ->orderBy('c.dateCommande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de commandes pour une année donnée
     * (utilisé pour générer le numéro séquentiel VG-YYYY-NNNN).
     */
    public function countCommandesAnnee(int $annee): int
    {
        $debut = new \DateTimeImmutable("$annee-01-01 00:00:00");
        $fin   = new \DateTimeImmutable(($annee + 1) . "-01-01 00:00:00");

        return (int)$this->createQueryBuilder('c')
            ->select('COUNT(c.commandeId)')
            ->where('c.dateCommande >= :debut')
            ->andWhere('c.dateCommande < :fin')
            ->setParameter('debut', $debut)
            ->setParameter('fin',   $fin)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Recherche employé/admin avec filtres.
     *
     * @param array<string, mixed> $filtres
     * @return Commande[]
     */
    public function rechercherEmploye(array $filtres = []): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.menu',        'm')->addSelect('m')
            ->leftJoin('c.utilisateur', 'u')->addSelect('u');

        $this->appliquerFiltresEmploye($qb, $filtres);

        return $qb->orderBy('c.dateCommande', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    private function appliquerFiltresEmploye(QueryBuilder $qb, array $filtres): void
    {
        if (!empty($filtres['statut'])) {
            $statut = $filtres['statut'] instanceof StatutCommande
                ? $filtres['statut']
                : StatutCommande::from($filtres['statut']);
            $qb->andWhere('c.statut = :statut')->setParameter('statut', $statut);
        }

        if (!empty($filtres['clientQuery'])) {
            $qb->andWhere('LOWER(u.prenom) LIKE :q OR LOWER(u.nom) LIKE :q OR LOWER(u.email) LIKE :q')
               ->setParameter('q', '%' . strtolower((string)$filtres['clientQuery']) . '%');
        }

        if (!empty($filtres['menuId'])) {
            $qb->andWhere('m.menuId = :menuId')->setParameter('menuId', (int)$filtres['menuId']);
        }

        if (!empty($filtres['datePrestation'])) {
            $date = $filtres['datePrestation'] instanceof \DateTimeInterface
                ? $filtres['datePrestation']
                : new \DateTimeImmutable((string)$filtres['datePrestation']);
            $qb->andWhere('c.datePrestation = :date')
               ->setParameter('date', $date->format('Y-m-d'));
        }
    }
}
