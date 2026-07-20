<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Enum\StatutAvis;
use App\Entity\Utilisateur;
use App\Repository\AvisRepository;
use App\Service\AvisSerializer;
use App\Service\AvisService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Endpoints avis pour la modération employé (et admin par héritage).
 *
 * Routes :
 *   GET    /api/employe/avis?statut=en_attente
 *   PUT    /api/employe/avis/{id}/valider
 *   DELETE /api/employe/avis/{id}/refuser
 */
#[Route('/api/employe/avis')]
#[IsGranted('ROLE_EMPLOYE')]
class AvisEmployeController extends AbstractController
{
    public function __construct(
        private readonly AvisService     $service,
        private readonly AvisRepository  $avis,
        private readonly AvisSerializer  $serializer,
    ) {
    }

    /**
     * GET /api/employe/avis?statut=en_attente|valide|refuse
     */
    #[Route('', methods: ['GET'])]
    public function liste(Request $request): JsonResponse
    {
        $statutBrut = $request->query->get('statut', 'en_attente');
        $statut = StatutAvis::tryFrom($statutBrut);
        if (!$statut) {
            return $this->json(['erreur' => 'Statut invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $avis = $this->avis->findByStatut($statut);
        return $this->json(
            array_map(fn($a) => $this->serializer->toArrayModeration($a), $avis)
        );
    }

    /**
     * PUT /api/employe/avis/{id}/valider
     */
    #[Route('/{id<\d+>}/valider', methods: ['PUT'])]
    public function valider(int $id, #[CurrentUser] Utilisateur $moderateur): JsonResponse
    {
        $avis = $this->avis->find($id);
        if (!$avis) {
            return $this->json(['erreur' => 'Avis introuvable.'], Response::HTTP_NOT_FOUND);
        }
        $this->service->validerAvis($avis, $moderateur);

        return $this->json([
            'message' => 'Avis validé — visible sur la page d\'accueil.',
            'avis'    => $this->serializer->toArrayModeration($avis),
        ]);
    }

    /**
     * DELETE /api/employe/avis/{id}/refuser
     */
    #[Route('/{id<\d+>}/refuser', methods: ['DELETE'])]
    public function refuser(int $id, #[CurrentUser] Utilisateur $moderateur): JsonResponse
    {
        $avis = $this->avis->find($id);
        if (!$avis) {
            return $this->json(['erreur' => 'Avis introuvable.'], Response::HTTP_NOT_FOUND);
        }
        $this->service->refuserAvis($avis, $moderateur);

        return $this->json(['message' => 'Avis refusé.']);
    }
}
