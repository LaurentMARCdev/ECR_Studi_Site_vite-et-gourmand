<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\HoraireDTO;
use App\Repository\HoraireRepository;
use App\Service\HoraireSerializer;
use App\Service\HoraireService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Endpoints horaires.
 *
 * Routes :
 *   GET /api/horaires                → public (footer, page contact)
 *   PUT /api/employe/horaires        → mise à jour en masse (employé/admin)
 */
class HoraireController extends AbstractController
{
    public function __construct(
        private readonly HoraireRepository   $horaires,
        private readonly HoraireSerializer   $serializer,
        private readonly HoraireService      $service,
        private readonly ValidatorInterface  $validator,
        private readonly SerializerInterface $symfonySerializer,
    ) {
    }

    /**
     * GET /api/horaires  (public)
     */
    #[Route('/api/horaires', methods: ['GET'])]
    public function liste(): JsonResponse
    {
        $horaires = $this->horaires->findAllOrdonnes();
        return $this->json(array_map(
            fn($h) => $this->serializer->toArray($h),
            $horaires
        ));
    }

    /**
     * PUT /api/employe/horaires  (auth : ROLE_EMPLOYE, admin par héritage)
     *
     * Body : tableau des 7 jours avec leurs horaires
     * [
     *   { horaire_id: 1, ferme: false, heureOuverture: "08:00", heureFermeture: "19:00" },
     *   { horaire_id: 7, ferme: true },
     *   ...
     * ]
     */
    #[Route('/api/employe/horaires', methods: ['PUT'])]
    #[IsGranted('ROLE_EMPLOYE')]
    public function mettreAJour(Request $request): JsonResponse
    {
        try {
            /** @var HoraireDTO[] $dtos */
            $dtos = $this->symfonySerializer->deserialize(
                $request->getContent(),
                HoraireDTO::class . '[]',
                'json'
            );
        } catch (\Throwable) {
            return $this->json(['erreur' => 'Corps de requête JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        // Valider chaque DTO du tableau
        foreach ($dtos as $index => $dto) {
            $violations = $this->validator->validate($dto);
            if (count($violations) > 0) {
                $errors = [];
                foreach ($violations as $v) {
                    $errors[$v->getPropertyPath()] = $v->getMessage();
                }
                return $this->json(
                    ['erreur' => sprintf('Horaire #%d invalide.', $index + 1), 'details' => $errors],
                    Response::HTTP_UNPROCESSABLE_ENTITY
                );
            }
        }

        try {
            $horaires = $this->service->mettreAJour($dtos);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        } catch (\DomainException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'message'  => 'Horaires mis à jour avec succès.',
            'horaires' => array_map(fn($h) => $this->serializer->toArray($h), $horaires),
        ]);
    }
}
