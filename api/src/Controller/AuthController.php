<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\InscriptionDTO;
use App\DTO\MotDePasseOublieDTO;
use App\DTO\ReinitialiserMdpDTO;
use App\Entity\Utilisateur;
use App\Service\AuthService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Endpoints d'authentification :
 *   POST   /api/auth/inscription
 *   POST   /api/auth/login              (géré par JsonLoginAuthenticator)
 *   POST   /api/auth/logout             (géré par Symfony)
 *   POST   /api/auth/mot-de-passe-oublie
 *   POST   /api/auth/reinitialiser
 *   GET    /api/auth/me
 *
 * Cette couche est volontairement fine :
 *  - Elle désérialise les DTO
 *  - Elle appelle le service métier
 *  - Elle formate la réponse HTTP
 *
 * Toute la logique métier est dans AuthService.
 */
#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthService         $auth,
        private readonly ValidatorInterface  $validator,
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface     $logger,
    ) {
    }

    /**
     * POST /api/auth/inscription
     * Body : { prenom, nom, email, gsm, adresse, motDePasse }
     */
    #[Route('/inscription', methods: ['POST'])]
    public function inscription(Request $request): JsonResponse
    {
        try {
            /** @var InscriptionDTO $dto */
            $dto = $this->serializer->deserialize(
                $request->getContent(),
                InscriptionDTO::class,
                'json'
            );
        } catch (\Throwable) {
            return $this->json(['erreur' => 'Corps de requête invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->json(
                ['erreur' => 'Données invalides.', 'details' => $this->formaterViolations($violations)],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        try {
            $user = $this->auth->inscrire($dto);
        } catch (\DomainException $e) {
            // Conflit (email déjà utilisé)
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_CONFLICT);
        } catch (\RuntimeException $e) {
            $this->logger->critical('Erreur inscription', ['exception' => $e->getMessage()]);
            return $this->json(['erreur' => 'Une erreur serveur est survenue.'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(
            [
                'message'        => 'Compte créé avec succès.',
                'utilisateur_id' => $user->getUtilisateurId(),
                'email'          => $user->getEmail(),
            ],
            Response::HTTP_CREATED
        );
    }

    /**
     * POST /api/auth/login
     *
     * Cette route N'EST PAS traitée par ce contrôleur : elle est interceptée
     * par le JsonLoginAuthenticator (voir security.yaml + JsonLoginAuthenticator).
     * On la déclare uniquement pour que Symfony la connaisse dans le routing.
     */
    #[Route('/login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // Ne devrait jamais être appelée : l'authenticator intercepte avant.
        throw new \LogicException(
            'Cette méthode ne devrait jamais être appelée : le JsonLoginAuthenticator gère /api/auth/login.'
        );
    }

    /**
     * POST /api/auth/logout
     *
     * Comme login : géré par Symfony (voir logout: dans security.yaml).
     * La déclaration ici sert uniquement au routing.
     */
    #[Route('/logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        throw new \LogicException('Cette méthode ne devrait jamais être appelée : Symfony gère le logout.');
    }

    /**
     * POST /api/auth/mot-de-passe-oublie
     * Body : { email }
     *
     * Toujours 200 pour ne pas révéler si l'e-mail existe.
     * Rate-limité à 3 requêtes / 15 min pour éviter l'énumération.
     */
    #[Route('/mot-de-passe-oublie', methods: ['POST'])]
    public function motDePasseOublie(
        Request              $request,
        RateLimiterFactory   $passwordResetLimiter,
    ): JsonResponse {
        // Rate limiting basé sur l'IP
        $limiter = $passwordResetLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(
                ['erreur' => 'Trop de tentatives. Réessayez dans quelques minutes.'],
                Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        try {
            /** @var MotDePasseOublieDTO $dto */
            $dto = $this->serializer->deserialize($request->getContent(), MotDePasseOublieDTO::class, 'json');
        } catch (\Throwable) {
            return $this->json(['erreur' => 'Corps de requête invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->json(
                ['erreur' => 'E-mail invalide.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $this->auth->demanderReinitialisationMdp($dto->email);

        // Réponse identique que l'e-mail existe ou non
        return $this->json([
            'message' => 'Si cette adresse est associée à un compte, un e-mail de réinitialisation vous a été envoyé.',
        ]);
    }

    /**
     * POST /api/auth/reinitialiser
     * Body : { token, nouveauMotDePasse }
     */
    #[Route('/reinitialiser', methods: ['POST'])]
    public function reinitialiser(Request $request): JsonResponse
    {
        try {
            /** @var ReinitialiserMdpDTO $dto */
            $dto = $this->serializer->deserialize($request->getContent(), ReinitialiserMdpDTO::class, 'json');
        } catch (\Throwable) {
            return $this->json(['erreur' => 'Corps de requête invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            return $this->json(
                ['erreur' => 'Données invalides.', 'details' => $this->formaterViolations($violations)],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        try {
            $this->auth->reinitialiserMdp($dto->token, $dto->nouveauMotDePasse);
        } catch (\DomainException $e) {
            return $this->json(['erreur' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['message' => 'Mot de passe mis à jour. Vous pouvez maintenant vous connecter.']);
    }

    /**
     * GET /api/auth/me
     *
     * Retourne les infos de l'utilisateur connecté (utile pour vérifier la session
     * côté front et récupérer prénom, rôle, etc.).
     */
    #[Route('/me', methods: ['GET'])]
    public function me(#[CurrentUser] ?Utilisateur $user): JsonResponse
    {
        if (!$user) {
            return $this->json(['erreur' => 'Non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'utilisateur_id' => $user->getUtilisateurId(),
            'prenom'         => $user->getPrenom(),
            'nom'            => $user->getNom(),
            'email'          => $user->getEmail(),
            'gsm'            => $user->getTelephone(),
            'adresse'        => $user->getAdressePostale(),
            'role'           => $user->getRole()->getLibelle(),
        ]);
    }

    /**
     * Convertit les violations de validation en tableau simple pour la réponse JSON.
     */
    private function formaterViolations($violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[$violation->getPropertyPath()] = $violation->getMessage();
        }
        return $errors;
    }
}
