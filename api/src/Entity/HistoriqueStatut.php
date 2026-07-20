<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\StatutCommande;
use App\Repository\HistoriqueStatutRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trace chronologique des transitions de statut d'une commande.
 *
 * Le sujet exige : "Le suivi de la commande énumère tous les états de sa
 * commande suivi de la date et l'heure de modification."
 *
 * → Cette table dédiée permet d'afficher facilement la timeline côté client
 *   sans devoir dénormaliser dans la commande elle-même.
 */
#[ORM\Entity(repositoryClass: HistoriqueStatutRepository::class)]
#[ORM\Table(name: 'historique_statut_commande')]
class HistoriqueStatut
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'historique_id', type: 'integer')]
    private ?int $historiqueId = null;

    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'historiqueStatuts')]
    #[ORM\JoinColumn(name: 'commande_id', referencedColumnName: 'commande_id', nullable: false, onDelete: 'CASCADE')]
    private Commande $commande;

    #[ORM\Column(name: 'statut', type: 'string', enumType: StatutCommande::class, length: 30)]
    private StatutCommande $statut;

    #[ORM\Column(name: 'date_changement', type: 'datetime_immutable')]
    private \DateTimeImmutable $dateChangement;

    public function getHistoriqueId(): ?int { return $this->historiqueId; }

    public function getCommande(): Commande { return $this->commande; }
    public function setCommande(Commande $c): self { $this->commande = $c; return $this; }

    public function getStatut(): StatutCommande { return $this->statut; }
    public function setStatut(StatutCommande $s): self { $this->statut = $s; return $this; }

    public function getDateChangement(): \DateTimeImmutable { return $this->dateChangement; }
    public function setDateChangement(\DateTimeImmutable $d): self { $this->dateChangement = $d; return $this; }
}
