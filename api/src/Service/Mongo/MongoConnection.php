<?php

declare(strict_types=1);

namespace App\Service\Mongo;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

/**
 * Connexion à la base NoSQL MongoDB.
 *
 * Encapsule le client MongoDB\Client pour :
 *  - Fournir un point d'accès unique aux collections
 *  - Gérer les échecs de connexion en douceur (fail-safe)
 *    → une panne de MongoDB ne doit JAMAIS faire tomber l'API principale
 *
 * L'usage recommandé est via :
 *   $collection = $connection->collection('statistiques_commandes');
 */
class MongoConnection
{
    private ?Client $client = null;
    private bool $tentativeEchouee = false;

    public function __construct(
        private readonly string          $mongoUrl,
        private readonly string          $mongoDb,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Retourne la collection demandée.
     * @throws \RuntimeException si la connexion échoue (l'appelant décide s'il gère ou re-throw)
     */
    public function collection(string $nom): Collection
    {
        return $this->database()->selectCollection($nom);
    }

    /**
     * Vérifie que la connexion est disponible sans lever d'exception.
     * Utile pour décider d'utiliser le fallback SQL.
     */
    public function estDisponible(): bool
    {
        if ($this->tentativeEchouee) {
            return false;
        }
        try {
            $this->database()->command(['ping' => 1]);
            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('MongoDB indisponible', ['exception' => $e->getMessage()]);
            $this->tentativeEchouee = true;
            return false;
        }
    }

    private function database(): Database
    {
        return $this->client()->selectDatabase($this->mongoDb);
    }

    private function client(): Client
    {
        if ($this->client === null) {
            $this->client = new Client($this->mongoUrl, [], [
                // Timeouts courts pour ne pas bloquer l'API principale
                'serverSelectionTimeoutMS' => 2000,
                'connectTimeoutMS'         => 2000,
                'socketTimeoutMS'          => 3000,
            ]);
        }
        return $this->client;
    }
}
