<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\HoraireDTO;
use App\Repository\HoraireRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Logique métier pour la gestion des horaires d'ouverture.
 *
 * L'employé/admin met à jour les 7 jours en une seule requête (opération
 * atomique via transaction Doctrine) — soit tout passe, soit rien.
 */
class HoraireService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HoraireRepository      $horaires,
    ) {
    }

    /**
     * Met à jour l'ensemble des horaires en une opération atomique.
     *
     * @param HoraireDTO[] $dtos
     *
     * @throws \InvalidArgumentException si un ID est inconnu
     * @throws \DomainException          si les heures sont incohérentes
     */
    public function mettreAJour(array $dtos): array
    {
        $this->em->beginTransaction();

        try {
            // Récupère tous les horaires en une requête pour éviter N+1
            $horaires  = $this->horaires->findAllOrdonnes();
            $parId     = [];
            foreach ($horaires as $h) {
                $parId[$h->getHoraireId()] = $h;
            }

            foreach ($dtos as $dto) {
                if (!isset($parId[$dto->horaireId])) {
                    throw new \InvalidArgumentException(
                        sprintf('Horaire #%d introuvable.', $dto->horaireId)
                    );
                }
                $horaire = $parId[$dto->horaireId];

                if ($dto->ferme) {
                    $horaire->marquerFerme();
                } else {
                    if (!$dto->heureOuverture || !$dto->heureFermeture) {
                        throw new \DomainException(
                            'Un jour ouvert doit avoir des heures d\'ouverture et de fermeture.'
                        );
                    }
                    $horaire->marquerOuvert(
                        new \DateTimeImmutable($dto->heureOuverture),
                        new \DateTimeImmutable($dto->heureFermeture),
                    );
                }
            }

            $this->em->flush();
            $this->em->commit();

        } catch (\Throwable $e) {
            $this->em->rollback();
            throw $e;
        }

        return $this->horaires->findAllOrdonnes();
    }
}
