<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Allergene;
use App\Entity\Avis;
use App\Entity\Commande;
use App\Entity\Enum\StatutAvis;
use App\Entity\Enum\StatutCommande;
use App\Entity\ImageMenu;
use App\Entity\Menu;
use App\Entity\Plat;
use App\Entity\Regime;
use App\Entity\Role;
use App\Entity\Theme;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Charge un jeu de données de démonstration (environnement de développement).
 *
 * Contenu : 3 comptes (client, employé, administrateur), 9 plats, 4 menus,
 * 8 commandes réparties sur tout le cycle de vie et 2 avis.
 *
 * Les commandes sont créées via Doctrine : le CommandeStatisticsListener
 * les recopie donc automatiquement dans MongoDB, ce qui alimente le
 * tableau de bord statistiques dès le premier démarrage.
 *
 * Les référentiels (rôles, thèmes, régimes, allergènes, horaires) sont
 * déjà insérés par les migrations Doctrine.
 *
 * Idempotente : si un menu existe déjà, la commande ne fait rien.
 *
 * Usage : php bin/console app:seed-demo
 *         (mots de passe lus dans DEMO_PASSWORD_CLIENT / _EMPLOYE / _ADMIN)
 */
#[AsCommand(
    name: 'app:seed-demo',
    description: 'Charge les données de démonstration (comptes, plats, menus, commandes, avis).',
)]
class SeedDemoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface      $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Charger même si des menus existent déjà');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$input->getOption('force') && $this->em->getRepository(Menu::class)->count([]) > 0) {
            $io->note('Des menus existent déjà : données de démonstration non rechargées.');
            return Command::SUCCESS;
        }

        $mdp = [
            Role::UTILISATEUR    => getenv('DEMO_PASSWORD_CLIENT') ?: null,
            Role::EMPLOYE        => getenv('DEMO_PASSWORD_EMPLOYE') ?: null,
            Role::ADMINISTRATEUR => getenv('DEMO_PASSWORD_ADMIN') ?: null,
        ];
        foreach ($mdp as $role => $valeur) {
            if ($valeur === null) {
                $io->error(sprintf('Mot de passe de démonstration manquant pour le rôle "%s" (variables DEMO_PASSWORD_*).', $role));
                return Command::FAILURE;
            }
        }

        $this->em->wrapInTransaction(function () use ($mdp, $io): void {
            // ── Comptes ───────────────────────────────────────────
            $client = $this->compte('client@vitegourmand.fr', 'Marie', 'Dupont', Role::UTILISATEUR, $mdp[Role::UTILISATEUR]);
            $this->compte('employe@vitegourmand.fr', 'Julie', 'Martin', Role::EMPLOYE, $mdp[Role::EMPLOYE]);
            $admin = $this->compte('admin@vitegourmand.fr', 'José', 'Rodriguez', Role::ADMINISTRATEUR, $mdp[Role::ADMINISTRATEUR]);

            // ── Plats ─────────────────────────────────────────────
            $p = [];
            foreach ([
                ['Velouté de potimarron aux graines de courge', Plat::CATEGORIE_ENTREE, ['Lactose']],
                ['Tartare de saumon frais à l\'aneth',          Plat::CATEGORIE_ENTREE, ['Poisson', 'Gluten']],
                ['Foie gras mi-cuit et chutney de figues',      Plat::CATEGORIE_ENTREE, ['Sulfites']],
                ['Filet de bœuf sauce bordelaise',              Plat::CATEGORIE_PLAT_PRINCIPAL, ['Sulfites', 'Céleri']],
                ['Risotto crémeux aux champignons des bois',    Plat::CATEGORIE_PLAT_PRINCIPAL, ['Lactose']],
                ['Tajine de légumes aux épices douces',         Plat::CATEGORIE_PLAT_PRINCIPAL, []],
                ['Cannelé bordelais artisanal',                 Plat::CATEGORIE_DESSERT, ['Gluten', 'Œufs', 'Lactose', 'Alcool']],
                ['Tiramisu revisité aux fruits rouges',         Plat::CATEGORIE_DESSERT, ['Gluten', 'Œufs', 'Lactose']],
                ['Mousse au chocolat noir et lait de coco',     Plat::CATEGORIE_DESSERT, ['Soja']],
            ] as [$titre, $categorie, $allergenes]) {
                $plat = (new Plat())->setTitre($titre)->setCategorie($categorie);
                foreach ($allergenes as $libelle) {
                    $plat->addAllergene($this->ref(Allergene::class, $libelle));
                }
                $this->em->persist($plat);
                $p[$titre] = $plat;
            }

            // ── Menus ─────────────────────────────────────────────
            $menus = [];
            foreach ([
                ['Menu de Noël Prestige', 'Foie gras, filet de bœuf bordelaise et cannelés pour des fêtes gourmandes.', 42.0, 8, 10, ['Noël'], ['classique'],
                    ['Foie gras mi-cuit et chutney de figues', 'Filet de bœuf sauce bordelaise', 'Cannelé bordelais artisanal'], 'Menu1.png'],
                ['Menu de mariage printanier', 'Trois services élégants à base de produits frais et locaux pour célébrer les unions.', 20.0, 10, null, ['classique', 'évènement'], ['classique', 'végétarien'],
                    ['Velouté de potimarron aux graines de courge', 'Risotto crémeux aux champignons des bois', 'Tiramisu revisité aux fruits rouges'], 'Menu2.png'],
                ['Brunch de Pâques', 'Saumon, risotto et douceurs fruitées pour un brunch de printemps.', 28.0, 6, 15, ['Pâques'], ['classique'],
                    ['Tartare de saumon frais à l\'aneth', 'Risotto crémeux aux champignons des bois', 'Tiramisu revisité aux fruits rouges'], 'Menu3.png'],
                ['Menu Vegan Terre & Saveurs', 'Un menu 100 % végétal, coloré et parfumé.', 22.0, 4, null, ['classique'], ['vegan', 'végétarien'],
                    ['Velouté de potimarron aux graines de courge', 'Tajine de légumes aux épices douces', 'Mousse au chocolat noir et lait de coco'], 'Menu4.png'],
            ] as [$titre, $description, $prix, $min, $stock, $themes, $regimes, $plats, $image]) {
                $menu = (new Menu())
                    ->setTitre($titre)
                    ->setDescription($description)
                    ->setPrixParPersonne($prix)
                    ->setNombrePersonneMinimum($min)
                    ->setQuantiteRestante($stock)
                    ->setConditions('Commande à passer au minimum 7 jours avant la prestation.')
                    ->setActif(true);
                foreach ($themes as $libelle) {
                    $menu->addTheme($this->ref(Theme::class, $libelle));
                }
                foreach ($regimes as $libelle) {
                    $menu->addRegime($this->ref(Regime::class, $libelle));
                }
                foreach ($plats as $titrePlat) {
                    $menu->addPlat($p[$titrePlat]);
                }
                $menu->addImage(
                    (new ImageMenu())->setUrl('/media/menus/' . $image)->setAltText($titre)->setEstPrincipale(true)
                );
                $this->em->persist($menu);
                $menus[$titre] = $menu;
            }
            $this->em->flush();

            // ── Commandes (une par étape du cycle de vie) ─────────
            $annee = (int) date('Y');
            $commandes = [];
            foreach ([
                ['Menu de Noël Prestige',      10, 'Bordeaux', 0,  StatutCommande::EN_ATTENTE],
                ['Menu de Noël Prestige',      14, 'Bordeaux', 0,  StatutCommande::ACCEPTE],
                ['Menu de mariage printanier', 40, 'Mérignac', 8,  StatutCommande::EN_PREPARATION],
                ['Brunch de Pâques',           6,  'Bordeaux', 0,  StatutCommande::EN_COURS_LIVRAISON],
                ['Menu Vegan Terre & Saveurs', 4,  'Pessac',   10, StatutCommande::LIVRE],
                ['Menu de mariage printanier', 25, 'Bordeaux', 0,  StatutCommande::ATTENTE_MATERIEL],
                ['Menu de Noël Prestige',      8,  'Talence',  6,  StatutCommande::TERMINEE],
                ['Brunch de Pâques',           12, 'Bordeaux', 0,  StatutCommande::TERMINEE],
            ] as $i => [$titreMenu, $personnes, $ville, $km, $statutCible]) {
                $commande = (new Commande())
                    ->setNumeroCommande(Commande::generateNumero($i + 1, $annee))
                    ->setUtilisateur($client)
                    ->setMenu($menus[$titreMenu])
                    ->setNombrePersonnes($personnes)
                    ->setDatePrestation(new \DateTimeImmutable(sprintf('+%d days', 10 + $i * 3)))
                    ->setHeureLivraison(new \DateTimeImmutable('12:00'))
                    ->setAdresseLivraison(sprintf('%d rue Sainte-Catherine', 10 + $i))
                    ->setVilleLivraison($ville)
                    ->setDistanceKm($km)
                    ->setPretMateriel($statutCible === StatutCommande::ATTENTE_MATERIEL)
                    ->setStatutInitial(StatutCommande::EN_ATTENTE);
                $commande->recalculerPrix();
                $this->avancerJusqua($commande, $statutCible);
                $this->em->persist($commande);
                $commandes[] = $commande;
            }
            $this->em->flush();

            // ── Avis (un publié, un en attente de modération) ──
            foreach ([
                [$commandes[6], 5, 'Foie gras divin et livraison parfaitement à l\'heure. Merci !', 'valider'],
                [$commandes[7], 4, 'Brunch copieux et très frais, les invités ont adoré.', null],
            ] as [$commande, $note, $texte, $action]) {
                $avis = (new Avis())
                    ->setUtilisateur($client)
                    ->setCommande($commande)
                    ->setNote($note)
                    ->setCommentaire($texte)
                    ->setStatut(StatutAvis::EN_ATTENTE);
                if ($action === 'valider') {
                    $avis->valider($admin);
                }
                $this->em->persist($avis);
            }
            $this->em->flush();

            $io->success(sprintf(
                '%d comptes, %d plats, %d menus, %d commandes et 2 avis créés.',
                3, count($p), count($menus), count($commandes)
            ));
        });

        return Command::SUCCESS;
    }

    private function compte(string $email, string $prenom, string $nom, string $role, string $motDePasse): Utilisateur
    {
        $repo = $this->em->getRepository(Utilisateur::class);
        $utilisateur = $repo->findOneBy(['email' => $email]) ?? new Utilisateur();
        $utilisateur
            ->setEmail($email)
            ->setPrenom($prenom)
            ->setNom($nom)
            ->setTelephone('0600000000')
            ->setAdressePostale('1 place de la Bourse, 33000 Bordeaux')
            ->setVille('Bordeaux')
            ->setPays('France')
            ->setRole($this->ref(Role::class, $role))
            ->setActif(true);
        $utilisateur->setPassword($this->hasher->hashPassword($utilisateur, $motDePasse));
        $this->em->persist($utilisateur);

        return $utilisateur;
    }

    /**
     * Fait passer la commande par toutes les étapes du flux jusqu'au statut voulu
     * (l'historique des statuts est ainsi cohérent).
     */
    private function avancerJusqua(Commande $commande, StatutCommande $cible): void
    {
        $chemin = [
            StatutCommande::ACCEPTE,
            StatutCommande::EN_PREPARATION,
            StatutCommande::EN_COURS_LIVRAISON,
            StatutCommande::LIVRE,
        ];
        if ($cible === StatutCommande::ATTENTE_MATERIEL) {
            $chemin[] = StatutCommande::ATTENTE_MATERIEL;
        } elseif ($cible === StatutCommande::TERMINEE) {
            $chemin[] = StatutCommande::TERMINEE;
        }

        foreach ($chemin as $statut) {
            if ($commande->getStatut() === $cible) {
                return;
            }
            $commande->changerStatut($statut);
        }
    }

    /**
     * @template T of object
     * @param class-string<T> $classe
     * @return T
     */
    private function ref(string $classe, string $libelle): object
    {
        $entite = $this->em->getRepository($classe)->findOneBy(['libelle' => $libelle]);
        if ($entite === null) {
            throw new \RuntimeException(sprintf('Référentiel introuvable : %s "%s".', $classe, $libelle));
        }
        return $entite;
    }
}
