<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\CommandeRepository;
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
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Synchronisation MongoDB depuis PostgreSQL');

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
        return Command::SUCCESS;
    }
}
