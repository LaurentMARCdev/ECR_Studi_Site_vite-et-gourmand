<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Avis;
use App\Entity\Commande;
use App\Entity\Enum\StatutAvis;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Avis>
 */
class AvisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Avis::class);
    }

    /**
     * Liste des avis validés (affichés publiquement sur la page d'accueil).
     * Triés par note descendante puis date descendante pour mettre en avant
     * les meilleurs avis récents.
     *
     * @return Avis[]
     */
    public function findAvisPublicsValides(int $limite = 6): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.utilisateur', 'u')->addSelect('u')
            ->leftJoin('a.commande',    'c')->addSelect('c')
            ->leftJoin('c.menu',        'm')->addSelect('m')
            ->where('a.statut = :statut')
            ->setParameter('statut', StatutAvis::VALIDE)
            ->orderBy('a.note',         'DESC')
            ->addOrderBy('a.dateCreation','DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste filtrable pour la modération.
     * @return Avis[]
     */
    public function findByStatut(StatutAvis $statut): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.utilisateur', 'u')->addSelect('u')
            ->leftJoin('a.commande',    'c')->addSelect('c')
            ->leftJoin('c.menu',        'm')->addSelect('m')
            ->where('a.statut = :statut')
            ->setParameter('statut', $statut)
            ->orderBy('a.dateCreation', 'ASC')  // Plus anciens d'abord (à traiter en priorité)
            ->getQuery()
            ->getResult();
    }

    /**
     * Un utilisateur a-t-il déjà déposé un avis sur cette commande ?
     * Utilisé pour l'unicité (la contrainte SQL est le garde-fou final,
     * mais on préfère renvoyer une erreur claire avant l'écriture).
     */
    public function existeAvisPourCommande(Commande $commande): bool
    {
        return $this->count(['commande' => $commande]) > 0;
    }

    /**
     * Récupère tous les avis d'un utilisateur (pour son espace personnel).
     * @return Avis[]
     */
    public function findByUtilisateur(Utilisateur $u): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.commande', 'c')->addSelect('c')
            ->leftJoin('c.menu',     'm')->addSelect('m')
            ->where('a.utilisateur = :u')
            ->setParameter('u', $u)
            ->orderBy('a.dateCreation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Note moyenne globale (calculée sur les avis validés seulement).
     * Utilisée par le dashboard admin.
     */
    public function calculerNoteMoyenne(): float
    {
        $moyenne = $this->createQueryBuilder('a')
            ->select('AVG(a.note)')
            ->where('a.statut = :statut')
            ->setParameter('statut', StatutAvis::VALIDE)
            ->getQuery()
            ->getSingleScalarResult();

        return round((float)($moyenne ?? 0), 1);
    }
}
