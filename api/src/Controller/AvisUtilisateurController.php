<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CreerAvisDTO;
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
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Endpoints avis pour l'utilisateur connecté.
 *
 * Routes :
 *   POST /api/avis            → déposer un avis
 *   GET  /api/avis/mes-avis   → lister mes avis
 */
#[Route('/api/avis')]
#[IsGranted('ROLE_UTILISATEUR')]
class AvisUtilisateurController extends AbstractController
{
    public function __construct(
        private readonly AvisService         $service,
        private readonly AvisRepository      $avis,
        private readonly AvisSerializer      $serializer,
        private readonly ValidatorInterface  $validator,
        private readonly SerializerInterface $symfonySerializer,
    ) {
    }

    /**
     * POST /api/avis
     * Body : { numeroCommande, note, commentaire }
     */
    #[Route('', methods: ['POST'])]
    public function deposer(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $dto = $this->deserialiser($request, CreerAvisDTO::class);
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->violationsToJson($violations);
        }

        try {
            $avis = $this->service->deposerAvis($user, $dto);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\RuntimeException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (\DomainException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json([
            'message' => 'Merci ! Votre avis sera publié après vérification par notre équipe.',
            'avis'    => $this->serializer->toArrayUtilisateur($avis),
        ], Response::HTTP_CREATED);
    }

    /**
     * GET /api/avis/mes-avis
     */
    #[Route('/mes-avis', methods: ['GET'])]
    public function mesAvis(#[CurrentUser] Utilisateur $user): JsonResponse
    {
        $mesAvis = $this->avis->findByUtilisateur($user);
        return $this->json(
            array_map(fn($a) => $this->serializer->toArrayUtilisateur($a), $mesAvis)
        );
    }

    // ═══════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════

    private function deserialiser(Request $request, string $class): object
    {
        try {
            return $this->symfonySerializer->deserialize($request->getContent(), $class, 'json');
        } catch (\Throwable) {
            return $this->json(['erreur' => 'Corps de requête JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }
    }

    private function violationsToJson($violations): JsonResponse
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()] = $violation->getMessage();
        }
        return $this->json(
            ['erreur' => 'Données invalides.', 'details' => $errors],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }
}
