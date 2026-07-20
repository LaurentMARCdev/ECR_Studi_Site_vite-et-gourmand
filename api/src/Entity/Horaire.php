<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\HoraireRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Horaire d'ouverture — table `horaire` du MCD (annexe 1).
 *
 * Modélisation retenue : une ligne par jour de la semaine (7 lignes fixes),
 * seedées à l'installation. Un employé ne peut PAS ajouter/supprimer
 * de lignes — il ne peut que modifier les valeurs des lignes existantes.
 *
 * Le champ `ferme` distingue les jours fermés (dimanche par défaut)
 * des jours ouverts (avec plages horaires renseignées).
 */
#[ORM\Entity(repositoryClass: HoraireRepository::class)]
#[ORM\Table(name: 'horaire')]
class Horaire
{
    public const JOURS_SEMAINE = [
        'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'horaire_id', type: 'integer')]
    private ?int $horaireId = null;

    #[ORM\Column(name: 'jour', type: 'string', length: 15, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: self::JOURS_SEMAINE, message: 'Jour invalide.')]
    private string $jour;

    /**
     * Ordre d'affichage : 1 (lundi) → 7 (dimanche).
     * Permet un ORDER BY simple et prévisible.
     */
    #[ORM\Column(name: 'ordre_jour', type: 'smallint', unique: true)]
    private int $ordreJour;

    /**
     * Format HH:MM (ex: "08:00"). Null si le jour est fermé.
     */
    #[ORM\Column(name: 'heure_ouverture', type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $heureOuverture = null;

    #[ORM\Column(name: 'heure_fermeture', type: 'time_immutable', nullable: true)]
    private ?\DateTimeImmutable $heureFermeture = null;

    #[ORM\Column(name: 'ferme', type: 'boolean', options: ['default' => false])]
    private bool $ferme = false;

    public function __construct(string $jour, int $ordreJour)
    {
        $this->jour      = $jour;
        $this->ordreJour = $ordreJour;
    }

    // ═══════════════════════════════════════════════════════════
    // Règles métier
    // ═══════════════════════════════════════════════════════════

    /**
     * Un jour fermé n'a pas d'heures d'ouverture — les remet à null.
     * Cohérence garantie même si on oublie de vider les heures côté client.
     */
    public function marquerFerme(): void
    {
        $this->ferme          = true;
        $this->heureOuverture = null;
        $this->heureFermeture = null;
    }

    /**
     * Ouvre le jour avec les heures spécifiées.
     *
     * @throws \DomainException si l'ouverture est ≥ à la fermeture
     */
    public function marquerOuvert(\DateTimeImmutable $ouverture, \DateTimeImmutable $fermeture): void
    {
        if ($ouverture >= $fermeture) {
            throw new \DomainException(
                sprintf('L\'heure de fermeture (%s) doit être postérieure à l\'ouverture (%s).',
                    $fermeture->format('H:i'), $ouverture->format('H:i'))
            );
        }
        $this->ferme          = false;
        $this->heureOuverture = $ouverture;
        $this->heureFermeture = $fermeture;
    }

    // ═══════════════════════════════════════════════════════════
    // GETTERS / SETTERS
    // ═══════════════════════════════════════════════════════════

    public function getHoraireId(): ?int { return $this->horaireId; }
    public function getJour(): string    { return $this->jour; }
    public function getOrdreJour(): int  { return $this->ordreJour; }

    public function getHeureOuverture(): ?\DateTimeImmutable { return $this->heureOuverture; }
    public function getHeureFermeture(): ?\DateTimeImmutable { return $this->heureFermeture; }

    public function isFerme(): bool { return $this->ferme; }
}
