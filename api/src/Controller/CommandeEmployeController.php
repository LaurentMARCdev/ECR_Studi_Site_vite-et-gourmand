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
 * Endpoints des commandes accessibles à l'employé et à l'administrateur.
 *
 * L'admin y accède aussi grâce à role_hierarchy dans security.yaml
 * (ROLE_ADMINISTRATEUR hérite de ROLE_EMPLOYE).
 *
 * Routes :
 *   GET    /api/employe/commandes                → liste avec filtres
 *   GET    /api/employe/commandes/{numero}       → détail complet
 *   PUT    /api/employe/commandes/{numero}/statut → transition de statut
 *   DELETE /api/employe/commandes/{numero}       → annulation avec motif
 */
#[Route('/api/employe/commandes')]
#[IsGranted('ROLE_EMPLOYE')]
class CommandeEmployeController extends AbstractController
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
     * GET /api/employe/commandes
     *
     * Query params (tous optionnels) :
     *   ?statut=en_attente                    → filtre par statut
     *   ?clientQuery=dupont                   → recherche nom/prénom/email
     *   ?menuId=3                             → filtre par menu
     *   ?datePrestation=2025-12-24            → filtre par date exacte
     */
    #[Route('', methods: ['GET'])]
    public function liste(Request $request): JsonResponse
    {
        $filtres = [
            'statut'         => $request->query->get('statut'),
            'clientQuery'    => $request->query->get('clientQuery'),
            'menuId'         => $request->query->get('menuId'),
            'datePrestation' => $request->query->get('datePrestation'),
        ];
        // Nettoyage des valeurs vides
        $filtres = array_filter($filtres, fn($v) => $v !== null && $v !== '');

        try {
            $commandes = $this->commandes->rechercherEmploye($filtres);
        } catch (\ValueError $e) {
            // Si un statut invalide est passé en query
            return $this->json(['erreur' => 'Statut de filtre invalide.'], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(
            array_map(fn($c) => $this->serializer->toArrayEspaceEmploye($c), $commandes)
        );
    }

    /**
     * GET /api/employe/commandes/{numero}
     */
    #[Route('/{numero}', methods: ['GET'], requirements: ['numero' => 'VG-\d{4}-\d{4}'])]
    public function detail(string $numero): JsonResponse
    {
        $commande = $this->commandes->findByNumero($numero);
        if (!$commande) {
            return $this->json(['erreur' => 'Commande introuvable.'], Response::HTTP_NOT_FOUND);
        }
        return $this->json($this->serializer->toArrayEspaceEmploye($commande));
    }

    /**
     * PUT /api/employe/commandes/{numero}/statut
     * Body : { "statut": "accepte" | "en_preparation" | ... }
     *
     * Fait progresser la commande vers le statut demandé. Le service refuse
     * automatiquement les transitions non autorisées (voir StatutCommande).
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
            $nouveauStatut = $dto->toStatutCommande();
            $this->service->transitionnerStatut($commande, $nouveauStatut);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\DomainException $e) {
            // Transition non autorisée (ex: livre → en_attente)
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json($this->serializer->toArrayEspaceEmploye($commande));
    }

    /**
     * DELETE /api/employe/commandes/{numero}
     * Body : { "modeContact": "gsm" | "mail", "motif": "..." }
     *
     * L'employé annule une commande. Le mode de contact et le motif sont
     * obligatoires (règle métier du sujet).
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
            'message' => 'Commande annulée.',
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
