<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Commande;
use App\Repository\CommandeRepository;
use App\Service\Mongo\MongoConnection;
use MongoDB\BSON\UTCDateTime;
use Psr\Log\LoggerInterface;

/**
 * Statistiques agrégées sur les commandes.
 *
 * ─────────────────────────────────────────────────────────────
 * ARCHITECTURE — pourquoi NoSQL ?
 * ─────────────────────────────────────────────────────────────
 * Le cahier des charges impose une BDD NoSQL en plus du relationnel :
 *   - PostgreSQL : source de vérité transactionnelle (ACID)
 *   - MongoDB    : stockage dénormalisé pour lectures rapides
 *                  (statistiques admin, agrégations flexibles)
 *
 * Chaque commande créée / mise à jour est synchronisée dans une
 * collection `statistiques_commandes` optimisée pour les lectures.
 *
 * En cas d'indisponibilité MongoDB, l'API :
 *   1. Log un warning
 *   2. Continue de fonctionner (les mutations ne sont PAS bloquées)
 *   3. Retombe sur une agrégation SQL pour les lectures
 * ─────────────────────────────────────────────────────────────
 */
class StatistiquesService
{
    public const COLLECTION = 'statistiques_commandes';

    public function __construct(
        private readonly MongoConnection    $mongo,
        private readonly CommandeRepository $commandes,
        private readonly LoggerInterface    $logger,
    ) {
    }

    // ═══════════════════════════════════════════════════════════
    // ÉCRITURES — synchronisation depuis PostgreSQL
    // ═══════════════════════════════════════════════════════════

    /**
     * Insère ou met à jour le document Mongo correspondant à une commande.
     * Appelé par le CommandeStatisticsListener après un persist/update Doctrine.
     */
    public function syncCommande(Commande $commande): void
    {
        try {
            $this->mongo->collection(self::COLLECTION)->updateOne(
                ['commande_id' => $commande->getCommandeId()],
                ['$set' => $this->commandeToDocument($commande)],
                ['upsert' => true],
            );
        } catch (\Throwable $e) {
            // Ne jamais faire tomber l'API pour un problème NoSQL
            $this->logger->error('Sync MongoDB échouée', [
                'commande_id' => $commande->getCommandeId(),
                'exception'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function commandeToDocument(Commande $c): array
    {
        return [
            'commande_id'     => $c->getCommandeId(),
            'numero_commande' => $c->getNumeroCommande(),
            'menu_id'         => $c->getMenu()->getMenuId(),
            'menu_titre'      => $c->getMenu()->getTitre(),
            'utilisateur_id'  => $c->getUtilisateur()->getUtilisateurId(),
            'statut'          => $c->getStatut()->value,
            'nombre_personnes'=> $c->getNombrePersonnes(),
            'prix_menu'       => $c->getPrixMenu(),
            'reduction'       => $c->getReduction(),
            'prix_livraison'  => $c->getPrixLivraison(),
            'prix_total'      => $c->getPrixTotal(),
            'date_commande'   => new UTCDateTime($c->getDateCommande()->getTimestamp() * 1000),
            'date_prestation' => new UTCDateTime($c->getDatePrestation()->getTimestamp() * 1000),
            'ville_livraison' => $c->getVilleLivraison(),
        ];
    }

    // ═══════════════════════════════════════════════════════════
    // LECTURES — agrégations MongoDB (avec fallback SQL)
    // ═══════════════════════════════════════════════════════════

    /**
     * Nombre de commandes groupées par menu.
     * Exclut les commandes annulées.
     *
     * @return array<int, array{menu: string, count: int}>
     */
    public function commandesParMenu(): array
    {
        if (!$this->mongo->estDisponible()) {
            return $this->commandesParMenuFallbackSQL();
        }

        try {
            $curseur = $this->mongo->collection(self::COLLECTION)->aggregate([
                ['$match' => ['statut' => ['$ne' => 'annulee']]],
                ['$group' => [
                    '_id'   => '$menu_titre',
                    'count' => ['$sum' => 1],
                ]],
                ['$sort'  => ['count' => -1]],
            ]);

            $resultats = [];
            foreach ($curseur as $doc) {
                $resultats[] = [
                    'menu'  => (string)$doc['_id'],
                    'count' => (int)$doc['count'],
                ];
            }
            return $resultats;

        } catch (\Throwable $e) {
            $this->logger->warning('Agrégation MongoDB échouée, fallback SQL', ['exception' => $e->getMessage()]);
            return $this->commandesParMenuFallbackSQL();
        }
    }

    /**
     * Chiffre d'affaires par menu, avec filtres optionnels de dates.
     *
     * @return array<int, array{menu: string, ca: float, count: int}>
     */
    public function chiffreAffairesParMenu(?\DateTimeInterface $debut = null, ?\DateTimeInterface $fin = null): array
    {
        if (!$this->mongo->estDisponible()) {
            return $this->chiffreAffairesFallbackSQL($debut, $fin);
        }

        $match = ['statut' => ['$nin' => ['annulee', 'en_attente']]];
        if ($debut) {
            $match['date_commande']['$gte'] = new UTCDateTime($debut->getTimestamp() * 1000);
        }
        if ($fin) {
            $match['date_commande']['$lte'] = new UTCDateTime($fin->getTimestamp() * 1000);
        }

        try {
            $curseur = $this->mongo->collection(self::COLLECTION)->aggregate([
                ['$match' => $match],
                ['$group' => [
                    '_id'   => '$menu_titre',
                    'ca'    => ['$sum' => '$prix_total'],
                    'count' => ['$sum' => 1],
                ]],
                ['$sort'  => ['ca' => -1]],
            ]);

            $resultats = [];
            foreach ($curseur as $doc) {
                $resultats[] = [
                    'menu'  => (string)$doc['_id'],
                    'ca'    => round((float)$doc['ca'], 2),
                    'count' => (int)$doc['count'],
                ];
            }
            return $resultats;

        } catch (\Throwable $e) {
            $this->logger->warning('Agrégation CA MongoDB échouée, fallback SQL', ['exception' => $e->getMessage()]);
            return $this->chiffreAffairesFallbackSQL($debut, $fin);
        }
    }

    // ═══════════════════════════════════════════════════════════
    // FALLBACKS SQL — utilisés si MongoDB indisponible
    // ═══════════════════════════════════════════════════════════

    /**
     * @return array<int, array{menu: string, count: int}>
     */
    private function commandesParMenuFallbackSQL(): array
    {
        $qb = $this->commandes->createQueryBuilder('c')
            ->select('m.titre AS menu, COUNT(c.commandeId) AS count')
            ->join('c.menu', 'm')
            ->where("c.statut != 'annulee'")
            ->groupBy('m.titre')
            ->orderBy('count', 'DESC');

        return array_map(
            fn($row) => ['menu' => (string)$row['menu'], 'count' => (int)$row['count']],
            $qb->getQuery()->getArrayResult()
        );
    }

    /**
     * @return array<int, array{menu: string, ca: float, count: int}>
     */
    private function chiffreAffairesFallbackSQL(?\DateTimeInterface $debut, ?\DateTimeInterface $fin): array
    {
        $qb = $this->commandes->createQueryBuilder('c')
            ->select('m.titre AS menu, SUM(c.prixTotal) AS ca, COUNT(c.commandeId) AS count')
            ->join('c.menu', 'm')
            ->where("c.statut NOT IN ('annulee', 'en_attente')")
            ->groupBy('m.titre')
            ->orderBy('ca', 'DESC');

        if ($debut) {
            $qb->andWhere('c.dateCommande >= :debut')->setParameter('debut', $debut);
        }
        if ($fin) {
            $qb->andWhere('c.dateCommande <= :fin')->setParameter('fin', $fin);
        }

        return array_map(
            fn($row) => [
                'menu'  => (string)$row['menu'],
                'ca'    => round((float)$row['ca'], 2),
                'count' => (int)$row['count'],
            ],
            $qb->getQuery()->getArrayResult()
        );
    }
}
