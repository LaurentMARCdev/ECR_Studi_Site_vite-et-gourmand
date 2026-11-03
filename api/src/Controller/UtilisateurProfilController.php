<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\UtilisateurProfilDTO;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Gestion du profil de l'utilisateur connecté (mon-espace).
 *
 * Un utilisateur authentifié (ROLE_UTILISATEUR ou rôles supérieurs par héritage)
 * peut consulter et modifier ses propres informations personnelles, y compris
 * son mot de passe.
 *
 * Sécurité :
 *  - L'utilisateur ne peut modifier QUE son propre profil, via #[CurrentUser]
 *    (impossible de passer un id arbitraire dans la requête)
 *  - Le changement de mot de passe exige le mot de passe actuel : défense
 *    contre le session hijacking.
 *  - Le rôle, le statut `actif` et l'id ne sont PAS modifiables ici
 *    (séparation des responsabilités avec AdminEmployeController)
 */
#[Route('/api/utilisateurs')]
#[IsGranted('ROLE_UTILISATEUR')]
class UtilisateurProfilController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface       $em,
        private readonly SerializerInterface          $serializer,
        private readonly ValidatorInterface           $validator,
        private readonly UserPasswordHasherInterface  $hasher,
    ) {}

    /**
     * GET /api/utilisateurs/profil
     *
     * Renvoie les informations du profil de l'utilisateur connecté.
     * Utilisé par le front pour pré-remplir le formulaire "Mon compte".
     *
     * Note sécurité :JAMAIS le mot de passe ni les tokens
     * de reset en retour — seuls les champs exposables sont sérialisés.
     */
    #[Route('/profil', methods: ['GET'])]
    public function recupererProfil(
        #[CurrentUser] Utilisateur $user,
    ): JsonResponse {
        return $this->json([
            'utilisateur_id'  => $user->getUtilisateurId(),
            'prenom'          => $user->getPrenom(),
            'nom'             => $user->getNom(),
            'email'           => $user->getEmail(),
            'gsm'             => $user->getTelephone(),
            'adresse'         => $user->getAdressePostale(),
            'ville'           => $user->getVille(),
            'pays'            => $user->getPays(),
            'date_creation'   => $user->getDateCreation()->format('Y-m-d'),
        ], Response::HTTP_OK);
    }

    /**
     * PUT /api/utilisateurs/profil
     *
     * Met à jour les informations personnelles de l'utilisateur connecté.
     * Permet aussi le changement de mot de passe si mdpActuel + mdpNouveau
     * sont fournis simultanément.
     *
     * Body attendu (JSON) :
     * {
     *   "prenom":     "Jean",
     *   "nom":        "Dupont",
     *   "email":      "jean@exemple.fr",
     *   "gsm":        "0612345678",
     *   "adresse":    "12 rue de la Paix, 75000 Paris",
     *   "mdpActuel":  "...",   // optionnel, requis SI mdpNouveau fourni
     *   "mdpNouveau": "..."    // optionnel
     * }
     *
     * Réponses :
     *   200 → { "message": "Profil mis à jour." }
     *   400 → { "erreur": "...", "details": { champ: "message" } }
     *   403 → { "erreur": "Mot de passe actuel incorrect." }
     *   409 → { "erreur": "Cette adresse e-mail est déjà utilisée." }
     */
    #[Route('/profil', methods: ['PUT'])]
    public function mettreAJourProfil(
        Request $request,
        #[CurrentUser] Utilisateur $user,
    ): JsonResponse {
        // ─── 1. Désérialisation du JSON reçu → DTO ──────────────────
        try {
            /** @var UtilisateurProfilDTO $dto */
            $dto = $this->serializer->deserialize(
                $request->getContent(),
                UtilisateurProfilDTO::class,
                'json'
            );
        } catch (\Throwable $e) {
            return $this->json(
                ['erreur' => 'Format de requête invalide.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // ─── 2. Validation des contraintes du DTO (Assert) ──────────
        $erreurs = $this->validator->validate($dto);
        if (count($erreurs) > 0) {
            $details = [];
            foreach ($erreurs as $e) {
                $details[$e->getPropertyPath()] = $e->getMessage();
            }
            return $this->json(
                ['erreur' => 'Données invalides.', 'details' => $details],
                Response::HTTP_BAD_REQUEST
            );
        }

        // ─── 3. Gestion du changement de mot de passe (optionnel) ───
        if ($dto->mdpNouveau !== null && $dto->mdpNouveau !== '') {
            // Le mdp actuel est OBLIGATOIRE pour en changer
            if (empty($dto->mdpActuel)) {
                return $this->json(
                    ['erreur' => 'Le mot de passe actuel est requis pour changer le mot de passe.'],
                    Response::HTTP_BAD_REQUEST
                );
            }

            // Vérification contre le hash en base (défense session hijacking)
            if (!$this->hasher->isPasswordValid($user, $dto->mdpActuel)) {
                return $this->json(
                    ['erreur' => 'Mot de passe actuel incorrect.'],
                    Response::HTTP_FORBIDDEN
                );
            }

            // Hashage du nouveau mdp (Argon2id configuré dans security.yaml)
            $user->setPassword($this->hasher->hashPassword($user, $dto->mdpNouveau));
        }

        // ─── 4. Vérification doublon email (si changement d'email) ──
        if ($dto->email !== $user->getEmail()) {
            $existant = $this->em->getRepository(Utilisateur::class)
                ->findOneBy(['email' => strtolower(trim($dto->email))]);
            if ($existant !== null
                && $existant->getUtilisateurId() !== $user->getUtilisateurId()) {
                return $this->json(
                    ['erreur' => 'Cette adresse e-mail est déjà utilisée.'],
                    Response::HTTP_CONFLICT
                );
            }
        }

        // ─── 5. Mise à jour des champs modifiables ──────────────────
        //       (JAMAIS le rôle, le statut `actif` ni l'id)
        $user
            ->setPrenom($dto->prenom)
            ->setNom($dto->nom)
            ->setEmail($dto->email)
            ->setTelephone($dto->telephone)
            ->setAdressePostale($dto->adressePostale);

        $this->em->flush();

        return $this->json(
            ['message' => 'Profil mis à jour.'],
            Response::HTTP_OK
        );
    }
}