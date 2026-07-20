<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AvisRepository;
use App\Service\AvisSerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Route publique pour afficher les avis sur la page d'accueil.
 *
 * GET /api/avis   → renvoie uniquement les avis VALIDÉS (règle du sujet).
 */
#[Route('/api/avis')]
class AvisPublicController extends AbstractController
{
    public function __construct(
        private readonly AvisRepository  $avis,
        private readonly AvisSerializer  $serializer,
    ) {
    }

    /**
     * GET /api/avis
     *
     * Query params optionnels :
     *   ?limite=6   (défaut 6, max 20)
     */
    #[Route('', methods: ['GET'])]
    public function liste(Request $request): JsonResponse
    {
        $limite = min(20, max(1, (int)$request->query->get('limite', 6)));

        $avis = $this->avis->findAvisPublicsValides($limite);

        return $this->json(
            array_map(fn($a) => $this->serializer->toArrayPublic($a), $avis)
        );
    }
}
