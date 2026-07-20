<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CreerEmployeDTO;
use App\Repository\UtilisateurRepository;
use App\Service\AdminService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Endpoints de gestion des employés — réservés à l'administrateur.
 *
 * Routes :
 *   GET  /api/admin/employes                     → liste des employés
 *   POST /api/admin/employes                     → créer un compte
 *   PUT  /api/admin/employes/{id}/toggle-actif   → activer/désactiver
 */
#[Route('/api/admin/employes')]
#[IsGranted('ROLE_ADMINISTRATEUR')]
class AdminEmployeController extends AbstractController
{
    public function __construct(
        private readonly AdminService          $service,
        private readonly UtilisateurRepository $utilisateurs,
        private readonly ValidatorInterface    $validator,
        private readonly SerializerInterface   $symfonySerializer,
    ) {
    }

    /**
     * GET /api/admin/employes
     */
    #[Route('', methods: ['GET'])]
    public function liste(): JsonResponse
    {
        $employes = $this->service->listerEmployes();

        return $this->json(array_map(fn($e) => [
            'utilisateur_id' => $e->getUtilisateurId(),
            'prenom'         => $e->getPrenom(),
            'nom'            => $e->getNom(),
            'email'          => $e->getEmail(),
            'actif'          => $e->isActif(),
            'date_creation'  => $e->getDateCreation()->format('Y-m-d'),
        ], $employes));
    }

    /**
     * POST /api/admin/employes
     * Body : { prenom, nom, email, motDePasse }
     */
    #[Route('', methods: ['POST'])]
    public function creer(Request $request): JsonResponse
    {
        $dto = $this->deserialiser($request, CreerEmployeDTO::class);
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->violationsToJson($violations);
        }

        try {
            $employe = $this->service->creerEmploye($dto);
        } catch (\DomainException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json([
            'message'        => 'Compte employé créé. Un e-mail de notification a été envoyé.',
            'utilisateur_id' => $employe->getUtilisateurId(),
            'email'          => $employe->getEmail(),
        ], Response::HTTP_CREATED);
    }

    /**
     * PUT /api/admin/employes/{id}/toggle-actif
     */
    #[Route('/{id<\d+>}/toggle-actif', methods: ['PUT'])]
    public function toggleActif(int $id): JsonResponse
    {
        $employe = $this->utilisateurs->find($id);
        if (!$employe) {
            return $this->json(['erreur' => 'Employé introuvable.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->service->toggleActifEmploye($employe);
        } catch (\DomainException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json([
            'message' => $employe->isActif() ? 'Compte réactivé.' : 'Compte désactivé.',
            'actif'   => $employe->isActif(),
        ]);
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
