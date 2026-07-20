<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\MenuDTO;
use App\DTO\PlatDTO;
use App\Entity\Menu;
use App\Entity\Plat;
use App\Repository\MenuRepository;
use App\Repository\PlatRepository;
use App\Service\MenuSerializer;
use App\Service\MenuService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Endpoints de gestion des menus et plats.
 *
 * Accès : ROLE_EMPLOYE minimum (admin hérite via role_hierarchy).
 *
 * Le préfixe /api/employe est cohérent avec la config d'access_control
 * dans security.yaml. L'admin utilisera les mêmes routes.
 *
 * Routes :
 *   POST   /api/employe/menus              → créer un menu
 *   PUT    /api/employe/menus/{id}         → modifier un menu
 *   DELETE /api/employe/menus/{id}         → désactiver un menu (soft delete)
 *
 *   GET    /api/employe/plats              → lister tous les plats
 *   POST   /api/employe/plats              → créer un plat
 *   PUT    /api/employe/plats/{id}         → modifier un plat
 *   DELETE /api/employe/plats/{id}         → supprimer un plat
 */
#[Route('/api/employe')]
#[IsGranted('ROLE_EMPLOYE')]
class MenuGestionController extends AbstractController
{
    public function __construct(
        private readonly MenuService         $service,
        private readonly MenuRepository      $menus,
        private readonly PlatRepository      $plats,
        private readonly MenuSerializer      $serializer,
        private readonly ValidatorInterface  $validator,
        private readonly SerializerInterface $symfonySerializer,
    ) {
    }

    // ═══════════════════════════════════════════════════════════
    // MENUS
    // ═══════════════════════════════════════════════════════════

    /**
     * POST /api/employe/menus
     */
    #[Route('/menus', methods: ['POST'])]
    public function creerMenu(Request $request): JsonResponse
    {
        $dto = $this->deserialiser($request, MenuDTO::class);
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->violationsToJson($violations);
        }

        try {
            $menu = $this->service->creerMenu($dto);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json(
            $this->serializer->toArrayDetail($menu),
            Response::HTTP_CREATED
        );
    }

    /**
     * PUT /api/employe/menus/{id}
     */
    #[Route('/menus/{id<\d+>}', methods: ['PUT'])]
    public function modifierMenu(int $id, Request $request): JsonResponse
    {
        $menu = $this->menus->find($id);
        if (!$menu) {
            return $this->json(['erreur' => 'Menu introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $dto = $this->deserialiser($request, MenuDTO::class);
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->violationsToJson($violations);
        }

        try {
            $menu = $this->service->modifierMenu($menu, $dto);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->serializer->toArrayDetail($menu));
    }

    /**
     * DELETE /api/employe/menus/{id}
     * Soft delete : préserve l'intégrité des commandes historiques.
     */
    #[Route('/menus/{id<\d+>}', methods: ['DELETE'])]
    public function supprimerMenu(int $id): JsonResponse
    {
        $menu = $this->menus->find($id);
        if (!$menu) {
            return $this->json(['erreur' => 'Menu introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $this->service->supprimerMenu($menu);

        return $this->json(['message' => 'Menu désactivé avec succès.']);
    }

    // ═══════════════════════════════════════════════════════════
    // PLATS
    // ═══════════════════════════════════════════════════════════

    /**
     * GET /api/employe/plats
     *
     * Query : ?categorie=entree|plat_principal|dessert (optionnel)
     */
    #[Route('/plats', methods: ['GET'])]
    public function listerPlats(Request $request): JsonResponse
    {
        $categorie = $request->query->get('categorie');

        $plats = $categorie
            ? $this->plats->findByCategorie($categorie)
            : $this->plats->findBy([], ['titre' => 'ASC']);

        return $this->json(
            array_map(fn(Plat $p) => $this->serializer->platToArray($p), $plats)
        );
    }

    /**
     * POST /api/employe/plats
     */
    #[Route('/plats', methods: ['POST'])]
    public function creerPlat(Request $request): JsonResponse
    {
        $dto = $this->deserialiser($request, PlatDTO::class);
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->violationsToJson($violations);
        }

        try {
            $plat = $this->service->creerPlat($dto);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->serializer->platToArray($plat), Response::HTTP_CREATED);
    }

    /**
     * PUT /api/employe/plats/{id}
     */
    #[Route('/plats/{id<\d+>}', methods: ['PUT'])]
    public function modifierPlat(int $id, Request $request): JsonResponse
    {
        $plat = $this->plats->find($id);
        if (!$plat) {
            return $this->json(['erreur' => 'Plat introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $dto = $this->deserialiser($request, PlatDTO::class);
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->violationsToJson($violations);
        }

        try {
            $plat = $this->service->modifierPlat($plat, $dto);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json($this->serializer->platToArray($plat));
    }

    /**
     * DELETE /api/employe/plats/{id}
     */
    #[Route('/plats/{id<\d+>}', methods: ['DELETE'])]
    public function supprimerPlat(int $id): JsonResponse
    {
        $plat = $this->plats->find($id);
        if (!$plat) {
            return $this->json(['erreur' => 'Plat introuvable.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->service->supprimerPlat($plat);
        } catch (\DomainException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json(['message' => 'Plat supprimé avec succès.']);
    }

    // ═══════════════════════════════════════════════════════════
    // Helpers
    // ═══════════════════════════════════════════════════════════

    /**
     * Désérialise le body JSON en DTO ou renvoie une erreur 400.
     */
    private function deserialiser(Request $request, string $class): object
    {
        try {
            return $this->symfonySerializer->deserialize(
                $request->getContent(),
                $class,
                'json'
            );
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
