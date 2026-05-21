<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Request\LoginRequestDTO;
use App\Dto\Request\RegisterRequestDTO;
use App\Dto\UserDTO;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailAlreadyUsedException;
use App\Service\UserRegistrationService;
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
        private readonly UserRepository $userRepository,
        private readonly UserRegistrationService $userRegistrationService,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $dto = RegisterRequestDTO::fromRequest($request);
        $errors = $this->validator->validate($dto);

        if (count($errors) > 0) {
            return $this->json(['error' => $errors[0]->getMessage()], 422);
        }

        try {
            $user = $this->userRegistrationService->register($dto);
        } catch (EmailAlreadyUsedException $e) {
            return $this->json(['error' => $e->getMessage()], 422);
        }

        return $this->json([
            'message' => 'Compte créé avec succès.',
            'token' => $this->jwtManager->create($user),
            'user' => UserDTO::fromEntity($user),
        ], 201);
    }

    #[Route('/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $dto = LoginRequestDTO::fromRequest($request);
        $errors = $this->validator->validate($dto);

        if (count($errors) > 0) {
            return $this->json(['error' => $errors[0]->getMessage()], 422);
        }

        $user = $this->userRepository->findOneBy(['email' => $dto->email]);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $dto->password)) {
            return $this->json(['error' => 'Identifiants invalides.'], 401);
        }

        return $this->json([
            'message' => 'Connexion réussie.',
            'token' => $this->jwtManager->create($user),
            'user' => UserDTO::fromEntity($user),
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

        return $this->json(UserDTO::fromEntity($user));
    }
}
