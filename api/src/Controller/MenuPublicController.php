<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\MenuRepository;
use App\Service\MenuSerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Endpoints publics (pas d'auth requise) pour lister et consulter les menus.
 *
 * Routes :
 *   GET /api/menus              → liste avec filtres
 *   GET /api/menus/{id}         → détail
 *   GET /api/menus/{id}/similaires → menus similaires
 */
#[Route('/api/menus')]
class MenuPublicController extends AbstractController
{
    public function __construct(
        private readonly MenuRepository  $menus,
        private readonly MenuSerializer  $serializer,
    ) {
    }

    /**
     * GET /api/menus
     *
     * Query params supportés (tous optionnels) :
     *   ?prixMin=X                 → prix total minimum
     *   ?prixMax=X                 → prix total maximum
     *   ?themeId=X                 → filtre par ID de thème
     *   ?themeLibelle=Noël         → filtre par nom de thème
     *   ?regimeId=X                → filtre par ID de régime
     *   ?regimeLibelle=vegan       → filtre par nom de régime
     *   ?personnesMin=N            → menus commandables pour ≤ N personnes minimum
     */
    #[Route('', methods: ['GET'])]
    public function liste(Request $request): JsonResponse
    {
        $filtres = [
            'prixMin'       => $request->query->get('prixMin'),
            'prixMax'       => $request->query->get('prixMax'),
            'themeId'       => $request->query->get('themeId'),
            'themeLibelle'  => $request->query->get('themeLibelle'),
            'regimeId'      => $request->query->get('regimeId'),
            'regimeLibelle' => $request->query->get('regimeLibelle'),
            'personnesMin'  => $request->query->get('personnesMin'),
        ];
        // Nettoyage des valeurs nulles/vides
        $filtres = array_filter($filtres, fn($v) => $v !== null && $v !== '');

        $menus = $this->menus->rechercher($filtres);

        return $this->json(
            array_map(fn($m) => $this->serializer->toArrayListe($m), $menus)
        );
    }

    /**
     * GET /api/menus/{id}
     */
    #[Route('/{id<\d+>}', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $menu = $this->menus->findActifWithRelations($id);
        if (!$menu) {
            return $this->json(
                ['erreur' => "Ce menu n'existe pas ou n'est plus disponible."],
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->json($this->serializer->toArrayDetail($menu));
    }

    /**
     * GET /api/menus/{id}/similaires
     * Retourne 3 autres menus (utilisé par la section "Vous aimerez aussi").
     */
    #[Route('/{id<\d+>}/similaires', methods: ['GET'])]
    public function similaires(int $id): JsonResponse
    {
        $similaires = $this->menus->findMenusPopulaires(3, $id);

        return $this->json(
            array_map(fn($m) => $this->serializer->toArrayListe($m), $similaires)
        );
    }
}
