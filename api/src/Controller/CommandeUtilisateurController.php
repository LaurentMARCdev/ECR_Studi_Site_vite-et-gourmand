<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CreerCommandeDTO;
use App\DTO\ModifierCommandeDTO;
use App\Entity\Utilisateur;
use App\Repository\CommandeRepository;
use App\Service\CommandeSerializer;
use App\Service\CommandeService;
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
 * Endpoints des commandes accessibles à l'utilisateur connecté.
 *
 * Routes :
 *   POST   /api/commandes
 *   GET    /api/commandes/mes-commandes
 *   GET    /api/commandes/{numero}
 *   PUT    /api/commandes/{numero}/modifier
 *   DELETE /api/commandes/{numero}
 */
#[Route('/api/commandes')]
#[IsGranted('ROLE_UTILISATEUR')]
class CommandeUtilisateurController extends AbstractController
{
    public function __construct(
        private readonly CommandeService     $service,
        private readonly CommandeRepository  $commandes,
        private readonly CommandeSerializer  $serializer,
        private readonly ValidatorInterface  $validator,
        private readonly SerializerInterface $symfonySerializer,
    ) {
    }

    /**
     * POST /api/commandes
     */
    #[Route('', methods: ['POST'])]
    public function creer(Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $dto = $this->deserialiser($request, CreerCommandeDTO::class);
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->violationsToJson($violations);
        }

        try {
            $commande = $this->service->creer($user, $dto);
        } catch (\DomainException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            return $this->json(['erreur' => 'Erreur lors de la création : ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(
            $this->serializer->toArrayEspaceClient($commande),
            Response::HTTP_CREATED
        );
    }

    /**
     * GET /api/commandes/mes-commandes
     */
    #[Route('/mes-commandes', methods: ['GET'])]
    public function mesCommandes(#[CurrentUser] Utilisateur $user): JsonResponse
    {
        $commandes = $this->commandes->findByUtilisateur($user);
        return $this->json(
            array_map(fn($c) => $this->serializer->toArrayEspaceClient($c), $commandes)
        );
    }

    /**
     * GET /api/commandes/{numero}
     */
    #[Route('/{numero}', methods: ['GET'], requirements: ['numero' => 'VG-\d{4}-\d{4}'])]
    public function detail(string $numero, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $commande = $this->commandes->findByNumero($numero);
        if (!$commande) {
            return $this->json(['erreur' => 'Commande introuvable.'], Response::HTTP_NOT_FOUND);
        }
        // Un utilisateur ne peut voir que ses propres commandes
        if ($commande->getUtilisateur()->getUtilisateurId() !== $user->getUtilisateurId()) {
            return $this->json(['erreur' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        return $this->json($this->serializer->toArrayEspaceClient($commande));
    }

    /**
     * PUT /api/commandes/{numero}/modifier
     */
    #[Route('/{numero}/modifier', methods: ['PUT'], requirements: ['numero' => 'VG-\d{4}-\d{4}'])]
    public function modifier(string $numero, Request $request, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $commande = $this->commandes->findByNumero($numero);
        if (!$commande) {
            return $this->json(['erreur' => 'Commande introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $dto = $this->deserialiser($request, ModifierCommandeDTO::class);
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->violationsToJson($violations);
        }

        try {
            $commande = $this->service->modifierParUtilisateur($commande, $user, $dto);
        } catch (\RuntimeException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (\DomainException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json($this->serializer->toArrayEspaceClient($commande));
    }

    /**
     * DELETE /api/commandes/{numero}
     */
    #[Route('/{numero}', methods: ['DELETE'], requirements: ['numero' => 'VG-\d{4}-\d{4}'])]
    public function annuler(string $numero, #[CurrentUser] Utilisateur $user): JsonResponse
    {
        $commande = $this->commandes->findByNumero($numero);
        if (!$commande) {
            return $this->json(['erreur' => 'Commande introuvable.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->service->annulerParUtilisateur($commande, $user);
        } catch (\RuntimeException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_FORBIDDEN);
        } catch (\DomainException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json(['message' => 'Commande annulée.']);
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
