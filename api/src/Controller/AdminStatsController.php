<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\StatistiquesService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoints de statistiques admin.
 *
 * Ces endpoints s'appuient sur la base NoSQL MongoDB 
 * avec fallback SQL automatique si Mongo est indisponible.
 *
 * Routes :
 *   GET /api/admin/stats/commandes-par-menu    → nb de commandes par menu
 *   GET /api/admin/stats/chiffre-affaires      → CA par menu (filtrable)
 */
#[Route('/api/admin/stats')]
#[IsGranted('ROLE_ADMINISTRATEUR')]
class AdminStatsController extends AbstractController
{
    public function __construct(
        private readonly StatistiquesService $stats,
    ) {
    }

    /**
     * GET /api/admin/stats/commandes-par-menu
     *
     * Réponse : [{ menu: "Menu de Noël Prestige", count: 42 }, ...]
     */
    #[Route('/commandes-par-menu', methods: ['GET'])]
    public function commandesParMenu(): JsonResponse
    {
        return $this->json($this->stats->commandesParMenu());
    }

    /**
     * GET /api/admin/stats/chiffre-affaires
     *
     * Query params optionnels :
     *   ?debut=2026-01-01
     *   ?fin=2026-12-31
     *
     * Réponse : [{ menu: "...", ca: 1830.50, count: 42 }, ...]
     */
    #[Route('/chiffre-affaires', methods: ['GET'])]
    public function chiffreAffaires(Request $request): JsonResponse
    {
        $debut = $this->parseDate($request->query->get('debut'));
        $fin   = $this->parseDate($request->query->get('fin'), true);
        //true: pour inclure toute la journée du dernier jour (23:59:59)
        return $this->json($this->stats->chiffreAffairesParMenu($debut, $fin));
    }
    /**
    * Correction du 30/10/26
    * Parse une date depuis une query string.
    *
    * @ param bool $finDeJournee Si true, ajuste à 23:59:59 pour inclure
    *                           toute la journée du dernier jour.
    *                           Sans ça, un filtre jusqu'au 2026-12-31
    *                           s'arrêterait à 00:00:00 et oublierait
    *                           TOUTES les commandes du 31 décembre.
    */
    private function parseDate(?string $raw, bool $finDeJournee = false): ?\DateTimeImmutable
    {
        if (!$raw) {
            return null;
        }
        try {
            $date = new \DateTimeImmutable($raw);
            if ($finDeJournee) {
                $date = $date->setTime(23, 59, 59);
            }
            return $date;
        } catch (\Throwable) {
            return null;
        }
    }
}
