<?php

namespace App\Controller\Api;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private ValidatorInterface $validator,
    ) {}

    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['password'])) {
            return $this->json([
                'error' => 'Email et mot de passe requis.',
            ], 422);
        }

        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if ($existing) {
            return $this->json([
                'error' => 'Cet email est déjà utilisé.',
            ], 422);
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'error' => 'Format d\'email invalide.',
            ], 422);
        }

        if (strlen($data['password']) < 8) {
            return $this->json([
                'error' => 'Le mot de passe doit contenir au moins 8 caractères.',
            ], 422);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $data['password'])
        );
        $user->setAlertsEnabled($data['alertsEnabled'] ?? true);

        $this->em->persist($user);
        $this->em->flush();

        $token = $this->jwtManager->create($user);

        return $this->json([
            'message' => 'Compte créé avec succès.',
            'token'   => $token,
            'user'    => [
                'id'            => $user->getId(),
                'email'         => $user->getEmail(),
                'alertsEnabled' => $user->isAlertsEnabled(),
                'createdAt'     => $user->getCreatedAt()->format('c'),
            ],
        ], 201);
    }

    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email']) || empty($data['password'])) {
            return $this->json([
                'error' => 'Email et mot de passe requis.',
            ], 422);
        }

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $data['email']]);
        if (!$user || !$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            return $this->json([
                'error' => 'Identifiants invalides.',
            ], 401);
        }

        $token = $this->jwtManager->create($user);

        return $this->json([
            'message' => 'Connexion réussie.',
            'token'   => $token,
            'user'    => [
                'id'            => $user->getId(),
                'email'         => $user->getEmail(),
                'alertsEnabled' => $user->isAlertsEnabled(),
                'createdAt'     => $user->getCreatedAt()->format('c'),
            ],
        ]);
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        if (!$user) {
            return $this->json(['error' => 'Non authentifié.'], 401);
        }

        return $this->json([
            'id'            => $user->getId(),
            'email'         => $user->getEmail(),
            'alertsEnabled' => $user->isAlertsEnabled(),
            'createdAt'     => $user->getCreatedAt()->format('c'),
        ]);
    }
}
