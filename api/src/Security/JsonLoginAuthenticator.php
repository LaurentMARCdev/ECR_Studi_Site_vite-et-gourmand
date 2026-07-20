<?php

declare(strict_types=1);

namespace App\Security;

use App\Repository\UtilisateurRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Authenticator qui accepte un JSON { "email": "...", "motDePasse": "..." }
 * sur POST /api/auth/login.
 *
 * Retourne du JSON, jamais de redirection.
 */
class JsonLoginAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(private readonly UtilisateurRepository $utilisateurs)
    {
    }

    /**
     * Ne se déclenche que sur POST /api/auth/login avec un body JSON.
     */
    public function supports(Request $request): ?bool
    {
        return $request->isMethod('POST')
            && $request->getPathInfo() === '/api/auth/login';
    }

    public function authenticate(Request $request): Passport
    {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            throw new CustomUserMessageAuthenticationException('Corps de requête JSON invalide.');
        }

        $email      = trim((string)($payload['email']      ?? ''));
        $motDePasse = (string)($payload['motDePasse'] ?? '');

        if ($email === '' || $motDePasse === '') {
            throw new CustomUserMessageAuthenticationException('E-mail et mot de passe requis.');
        }

        return new Passport(
            new UserBadge($email, function (string $identifier) {
                $user = $this->utilisateurs->findActifByEmail($identifier);
                if (!$user) {
                    // Message générique pour ne pas révéler si le compte existe
                    throw new CustomUserMessageAuthenticationException('Identifiants incorrects.');
                }
                return $user;
            }),
            new PasswordCredentials($motDePasse),
        );
    }

    /**
     * Succès : renvoie les infos utilisateur au front pour la redirection.
     */
    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        return new JsonResponse([
            'message'  => 'Connexion réussie.',
            'utilisateur_id' => $user->getUtilisateurId(),
            'prenom'   => $user->getPrenom(),
            'nom'      => $user->getNom(),
            'email'    => $user->getEmail(),
            'role'     => $user->getRole()->getLibelle(),
            // Suggestion de redirection front selon le rôle
            'redirect' => match ($user->getRole()->getLibelle()) {
                'administrateur' => '/admin',
                'employe'        => '/employe',
                default          => '/mon-espace',
            },
        ]);
    }

    /**
     * Échec : erreur générique en 401.
     * On ne divulgue jamais si c'est l'email ou le mot de passe qui est faux.
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            ['erreur' => $exception->getMessageKey() === 'Invalid credentials.'
                ? 'Identifiants incorrects.'
                : $exception->getMessage()],
            Response::HTTP_UNAUTHORIZED
        );
    }

    /**
     * Point d'entrée quand un utilisateur non authentifié tente une route protégée.
     */
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(
            ['erreur' => 'Authentification requise.'],
            Response::HTTP_UNAUTHORIZED
        );
    }
}
