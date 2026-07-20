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
 * Ces endpoints s'appuient sur la base NoSQL MongoDB (exigence du sujet)
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
     *   ?debut=2025-01-01
     *   ?fin=2025-12-31
     *
     * Réponse : [{ menu: "...", ca: 1830.50, count: 42 }, ...]
     */
    #[Route('/chiffre-affaires', methods: ['GET'])]
    public function chiffreAffaires(Request $request): JsonResponse
    {
        $debut = $this->parseDate($request->query->get('debut'));
        $fin   = $this->parseDate($request->query->get('fin'));

        return $this->json($this->stats->chiffreAffairesParMenu($debut, $fin));
    }

    private function parseDate(?string $raw): ?\DateTimeImmutable
    {
        if (!$raw) {
            return null;
        }
        try {
            return new \DateTimeImmutable($raw);
        } catch (\Throwable) {
            return null;
        }
    }
}
