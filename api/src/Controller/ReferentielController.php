<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AllergeneRepository;
use App\Repository\RegimeRepository;
use App\Repository\ThemeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoints publics de référentiels (listes utilisées pour peupler les
 * selects/filtres du front).
 *
 * Routes :
 *   GET /api/referentiels/themes
 *   GET /api/referentiels/regimes
 *   GET /api/referentiels/allergenes
 */
#[Route('/api/referentiels')]
class ReferentielController extends AbstractController
{
    public function __construct(
        private readonly ThemeRepository     $themes,
        private readonly RegimeRepository    $regimes,
        private readonly AllergeneRepository $allergenes,
    ) {
    }

    #[Route('/themes', methods: ['GET'])]
    public function themes(): JsonResponse
    {
        $themes = $this->themes->findBy([], ['libelle' => 'ASC']);
        return $this->json(array_map(
            fn($t) => ['theme_id' => $t->getThemeId(), 'libelle' => $t->getLibelle()],
            $themes
        ));
    }

    #[Route('/regimes', methods: ['GET'])]
    public function regimes(): JsonResponse
    {
        $regimes = $this->regimes->findBy([], ['libelle' => 'ASC']);
        return $this->json(array_map(
            fn($r) => ['regime_id' => $r->getRegimeId(), 'libelle' => $r->getLibelle()],
            $regimes
        ));
    }

    #[Route('/allergenes', methods: ['GET'])]
    public function allergenes(): JsonResponse
    {
        $allergenes = $this->allergenes->findBy([], ['libelle' => 'ASC']);
        return $this->json(array_map(
            fn($a) => ['allergene_id' => $a->getAllergeneId(), 'libelle' => $a->getLibelle()],
            $allergenes
        ));
    }
}
