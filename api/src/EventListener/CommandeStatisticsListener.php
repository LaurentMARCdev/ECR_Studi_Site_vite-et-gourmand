<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Commande;
use App\Service\StatistiquesService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Écoute les événements Doctrine sur l'entité Commande pour synchroniser
 * automatiquement la collection MongoDB des statistiques.
 *
 * Avantages :
 *  - Aucun couplage entre CommandeService et le monde NoSQL
 *  - Impossible d'oublier la sync (elle est déclenchée par Doctrine)
 *  - Le StatistiquesService encapsule le fail-safe (Mongo down → log + continue)
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
class CommandeStatisticsListener
{
    public function __construct(
        private readonly StatistiquesService $stats,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof Commande) {
            $this->stats->syncCommande($entity);
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof Commande) {
            $this->stats->syncCommande($entity);
        }
    }
}
