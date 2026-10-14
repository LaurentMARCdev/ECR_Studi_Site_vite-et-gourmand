<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\CommandeRepository;
use App\Service\Mongo\MongoConnection;
use App\Service\StatistiquesService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Commande CLI pour resynchroniser MongoDB depuis PostgreSQL.
 *
 * Utile pour :
 *  - Un déploiement initial (peupler MongoDB depuis les commandes existantes)
 *  - Une resynchronisation après une panne MongoDB
 *  - Une migration entre environnements
 *  - Démontrer l'accès réel à la base NoSQL : après l'écriture, la commande
 *    relit MongoDB (nombre de documents + agrégation "commandes par menu").
 *
 * Usage : php bin/console app:sync-mongo
 */
#[AsCommand(
    name: 'app:sync-mongo',
    description: 'Resynchronise la collection MongoDB des statistiques depuis PostgreSQL.',
)]
class SyncMongoCommand extends Command
{
    public function __construct(
        private readonly CommandeRepository  $commandes,
        private readonly StatistiquesService $stats,
        private readonly MongoConnection     $mongo,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Synchronisation MongoDB depuis PostgreSQL');

        // Sans ce contrôle, syncCommande() avalerait l'erreur (fail-safe de l'API)
        // et la commande annoncerait un succès alors que rien n'a été écrit.
        if (!$this->mongo->estDisponible()) {
            $io->error('MongoDB est injoignable (vérifier MONGODB_URL).');
            return Command::FAILURE;
        }

        $toutes = $this->commandes->findAll();
        $total = count($toutes);

        if ($total === 0) {
            $io->warning('Aucune commande en base — rien à synchroniser.');
            return Command::SUCCESS;
        }

        $io->progressStart($total);
        $ok = 0;
        foreach ($toutes as $commande) {
            $this->stats->syncCommande($commande);
            $io->progressAdvance();
            $ok++;
        }
        $io->progressFinish();

        $io->success(sprintf('%d commande(s) synchronisée(s) vers MongoDB.', $ok));

        // Relecture depuis MongoDB : preuve que les données sont bien dans la base NoSQL
        $nbDocuments = $this->mongo->collection(StatistiquesService::COLLECTION)->countDocuments();
        $io->section(sprintf('Relecture MongoDB : %d document(s) dans "%s"', $nbDocuments, StatistiquesService::COLLECTION));
        $io->table(
            ['Menu', 'Commandes (hors annulées)'],
            array_map(fn(array $ligne) => [$ligne['menu'], $ligne['count']], $this->stats->commandesParMenu())
        );

        return Command::SUCCESS;
    }
}
