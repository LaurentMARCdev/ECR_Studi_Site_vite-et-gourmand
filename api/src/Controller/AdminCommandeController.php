<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\AnnulerCommandeEmployeDTO;
use App\DTO\ChangerStatutDTO;
use App\Repository\CommandeRepository;
use App\Service\CommandeSerializer;
use App\Service\CommandeService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Contrôleur "alias" — expose les routes de gestion des commandes sous
 * le préfixe /api/admin/... attendu par le front admin.
 *
 * ─────────────────────────────────────────────────────────────
 * POURQUOI CE CONTRÔLEUR ?
 * ─────────────────────────────────────────────────────────────
 * Le CommandeEmployeController expose déjà les mêmes routes sous
 * /api/employe/commandes, accessibles à l'administrateur grâce à
 * role_hierarchy (ROLE_ADMINISTRATEUR hérite de ROLE_EMPLOYE).
 *
 * Toutefois, la page admin.html appelle explicitement /api/admin/commandes,
 * ce qui a du sens sémantiquement : l'admin a sa propre section
 * "Administration" qui ne doit pas dépendre du chemin des employés.
 *
 * Ce contrôleur délègue simplement au même service métier.
 * ─────────────────────────────────────────────────────────────
 */
#[Route('/api/admin/commandes')]
#[IsGranted('ROLE_ADMINISTRATEUR')]
class AdminCommandeController extends AbstractController
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
     * GET /api/admin/commandes
     */
    #[Route('', methods: ['GET'])]
    public function liste(Request $request): JsonResponse
    {
        $filtres = array_filter([
            'statut'         => $request->query->get('statut'),
            'clientQuery'    => $request->query->get('clientQuery'),
            'menuId'         => $request->query->get('menuId'),
            'datePrestation' => $request->query->get('datePrestation'),
        ], fn($v) => $v !== null && $v !== '');

        try {
            $commandes = $this->commandes->rechercherEmploye($filtres);
        } catch (\ValueError) {
            return $this->json(['erreur' => 'Statut de filtre invalide.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(
            array_map(fn($c) => $this->serializer->toArrayEspaceEmploye($c), $commandes)
        );
    }

    /**
     * PUT /api/admin/commandes/{numero}/statut
     */
    #[Route('/{numero}/statut', methods: ['PUT'], requirements: ['numero' => 'VG-\d{4}-\d{4}'])]
    public function changerStatut(string $numero, Request $request): JsonResponse
    {
        $commande = $this->commandes->findByNumero($numero);
        if (!$commande) {
            return $this->json(['erreur' => 'Commande introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $dto = $this->deserialiser($request, ChangerStatutDTO::class);
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->violationsToJson($violations);
        }

        try {
            $this->service->transitionnerStatut($commande, $dto->toStatutCommande());
        } catch (\InvalidArgumentException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\DomainException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json($this->serializer->toArrayEspaceEmploye($commande));
    }

    /**
     * DELETE /api/admin/commandes/{numero}
     */
    #[Route('/{numero}', methods: ['DELETE'], requirements: ['numero' => 'VG-\d{4}-\d{4}'])]
    public function annuler(string $numero, Request $request): JsonResponse
    {
        $commande = $this->commandes->findByNumero($numero);
        if (!$commande) {
            return $this->json(['erreur' => 'Commande introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $dto = $this->deserialiser($request, AnnulerCommandeEmployeDTO::class);
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->violationsToJson($violations);
        }

        try {
            $this->service->annulerParEmploye($commande, $dto);
        } catch (\DomainException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'message'  => 'Commande annulée.',
            'commande' => $this->serializer->toArrayEspaceEmploye($commande),
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
