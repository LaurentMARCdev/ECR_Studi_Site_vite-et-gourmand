<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Menu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Menu>
 */
class MenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Menu::class);
    }

    /**
     * Trouve un menu actif avec toutes ses relations chargées (fetch join).
     * Utilisé pour la page détail : évite les N+1 queries.
     */
    public function findActifWithRelations(int $menuId): ?Menu
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.themes',    't')->addSelect('t')
            ->leftJoin('m.regimes',   'r')->addSelect('r')
            ->leftJoin('m.plats',     'p')->addSelect('p')
            ->leftJoin('p.allergenes','a')->addSelect('a')
            ->leftJoin('m.images',    'i')->addSelect('i')
            ->where('m.menuId = :id')
            ->andWhere('m.actif = true')
            ->setParameter('id', $menuId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Liste tous les menus actifs avec filtres optionnels.
     *
     * Filtres supportés (correspondent aux filtres de la page /menus) :
     *   - prixMin, prixMax        : fourchette de prix total
     *   - themeId, regimeId       : filtre par référence
     *   - themeLibelle            : filtre par libellé (utile pour le front)
     *   - regimeLibelle           : idem
     *   - personnesMin            : nombre_personne_minimum ≤ personnesMin
     *   - inclureIndisponibles    : par défaut false (masque les épuisés)
     *
     * @param array<string, mixed> $filtres
     * @return Menu[]
     */
    public function rechercher(array $filtres = []): array
    {
        $qb = $this->createQueryBuilder('m')
            ->leftJoin('m.themes',  't')->addSelect('t')
            ->leftJoin('m.regimes', 'r')->addSelect('r')
            ->leftJoin('m.images',  'i')->addSelect('i')
            ->where('m.actif = true');

        $this->appliquerFiltres($qb, $filtres);

        // Ordre : menus disponibles d'abord, puis les plus récents
        $qb->orderBy('m.dateCreation', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Applique les filtres au QueryBuilder.
     */
    private function appliquerFiltres(QueryBuilder $qb, array $filtres): void
    {
        // ── Prix ────────────────────────────────────────────────
        // On filtre sur le prix "à partir de" (prix minimum) = prix_par_personne × nombre_personne_minimum
        if (isset($filtres['prixMin']) && $filtres['prixMin'] > 0) {
            $qb->andWhere('(m.prixParPersonne * m.nombrePersonneMinimum) >= :prixMin')
               ->setParameter('prixMin', (float)$filtres['prixMin']);
        }
        if (isset($filtres['prixMax']) && $filtres['prixMax'] > 0) {
            $qb->andWhere('(m.prixParPersonne * m.nombrePersonneMinimum) <= :prixMax')
               ->setParameter('prixMax', (float)$filtres['prixMax']);
        }

        // ── Thème ───────────────────────────────────────────────
        if (!empty($filtres['themeId'])) {
            // Sous-requête pour éviter que le JOIN ne casse les autres relations
            $qb->andWhere('EXISTS (
                SELECT 1 FROM App\Entity\Menu m2
                JOIN m2.themes t2
                WHERE m2 = m AND t2.themeId = :themeId
            )')->setParameter('themeId', (int)$filtres['themeId']);
        }
        if (!empty($filtres['themeLibelle'])) {
            $qb->andWhere('EXISTS (
                SELECT 1 FROM App\Entity\Menu m3
                JOIN m3.themes t3
                WHERE m3 = m AND t3.libelle = :themeLibelle
            )')->setParameter('themeLibelle', $filtres['themeLibelle']);
        }

        // ── Régime ──────────────────────────────────────────────
        if (!empty($filtres['regimeId'])) {
            $qb->andWhere('EXISTS (
                SELECT 1 FROM App\Entity\Menu m4
                JOIN m4.regimes r4
                WHERE m4 = m AND r4.regimeId = :regimeId
            )')->setParameter('regimeId', (int)$filtres['regimeId']);
        }
        if (!empty($filtres['regimeLibelle'])) {
            $qb->andWhere('EXISTS (
                SELECT 1 FROM App\Entity\Menu m5
                JOIN m5.regimes r5
                WHERE m5 = m AND r5.libelle = :regimeLibelle
            )')->setParameter('regimeLibelle', $filtres['regimeLibelle']);
        }

        // ── Nombre de personnes ─────────────────────────────────
        // "Filtre par nombre de personne minimum" : afficher les menus dont
        // le minimum requis est ≤ au nombre voulu par le client
        if (!empty($filtres['personnesMin'])) {
            $qb->andWhere('m.nombrePersonneMinimum <= :personnesMin')
               ->setParameter('personnesMin', (int)$filtres['personnesMin']);
        }

        // ── Disponibilité ───────────────────────────────────────
        // Par défaut on masque les menus épuisés
        if (empty($filtres['inclureIndisponibles'])) {
            $qb->andWhere('m.quantiteRestante IS NULL OR m.quantiteRestante > 0');
        }
    }

    /**
     * Menus les plus commandés (pour la section "Vous aimerez aussi").
     *
     * @return Menu[]
     */
    public function findMenusPopulaires(int $limite = 3, ?int $exclureId = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.actif = true')
            ->andWhere('m.quantiteRestante IS NULL OR m.quantiteRestante > 0')
            ->orderBy('m.dateCreation', 'DESC')
            ->setMaxResults($limite);

        if ($exclureId !== null) {
            $qb->andWhere('m.menuId != :exclu')->setParameter('exclu', $exclureId);
        }

        return $qb->getQuery()->getResult();
    }
}
