<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\ContactDTO;
use App\Service\MailerService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Endpoint public du formulaire de contact.
 *
 * POST /api/contact
 * Body : { titre, email, description }
 *
 * Sécurité :
 *  - Rate limiting : 5 requêtes / 15 minutes par IP (configuré dans framework.yaml)
 *  - Le message est envoyé à l'adresse contact de l'entreprise (.env)
 *  - Le champ Reply-To de l'e-mail est celui du visiteur → réponse facile
 *  - Aucune donnée n'est persistée en base : le service e-mail est le stockage
 */
#[Route('/api/contact')]
class ContactController extends AbstractController
{
    public function __construct(
        private readonly MailerService       $mailer,
        private readonly ValidatorInterface  $validator,
        private readonly SerializerInterface $symfonySerializer,
        private readonly LoggerInterface     $logger,
    ) {
    }

    /**
     * POST /api/contact
     */
    #[Route('', methods: ['POST'])]
    public function envoyer(
        Request            $request,
        RateLimiterFactory $contactFormLimiter,
    ): JsonResponse {
        // Rate limiting basé sur l'IP — évite le spam
        $limiter = $contactFormLimiter->create($request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(
                ['erreur' => 'Trop de messages envoyés. Réessayez dans quelques minutes.'],
                Response::HTTP_TOO_MANY_REQUESTS
            );
        }

        try {
            /** @var ContactDTO $dto */
            $dto = $this->symfonySerializer->deserialize($request->getContent(), ContactDTO::class, 'json');
        } catch (\Throwable) {
            return $this->json(['erreur' => 'Corps de requête JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $v) {
                $errors[$v->getPropertyPath()] = $v->getMessage();
            }
            return $this->json(
                ['erreur' => 'Données invalides.', 'details' => $errors],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        try {
            $this->mailer->envoyerMessageContact($dto->titre, $dto->email, $dto->description);
        } catch (\Throwable $e) {
            $this->logger->error('Échec envoi mail contact', [
                'email'     => $dto->email,
                'exception' => $e->getMessage(),
            ]);
            return $this->json(
                ['erreur' => 'Impossible d\'envoyer votre message pour le moment. Réessayez plus tard.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        return $this->json(
            ['message' => 'Votre message a bien été envoyé. Nous vous répondrons dans les meilleurs délais.'],
            Response::HTTP_CREATED
        );
    }
}
