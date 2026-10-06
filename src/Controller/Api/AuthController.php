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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
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
        private readonly RateLimiterFactoryInterface $apiLoginLimiter,
        private readonly RateLimiterFactoryInterface $registrationLimiter,
        #[Autowire('%app.registration_enabled%')]
        private readonly bool $registrationEnabled,
    ) {
    }

    #[Route('/register', name: 'api_auth_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        if (!$this->registrationEnabled) {
            return $this->json(['error' => 'Les inscriptions sont fermées.'], 403);
        }

        if (!$this->registrationLimiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
            return $this->json(['error' => 'Trop de tentatives, réessayez plus tard.'], 429);
        }

        $dto = RegisterRequestDTO::fromRequest($request);
        $errors = $this->validator->validate($dto);

        if (count($errors) > 0) {
            return $this->json(['error' => $errors[0]->getMessage()], 422);
        }

        try {
            $user = $this->userRegistrationService->register($dto);
        } catch (EmailAlreadyUsedException) {
            // Message neutre : ne pas confirmer qu'une adresse a déjà un compte.
            return $this->json(['error' => 'Inscription impossible avec ces informations.'], 422);
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

        $limiter = $this->apiLoginLimiter->create($request->getClientIp().'|'.mb_strtolower((string) $dto->email));
        if (!$limiter->consume()->isAccepted()) {
            return $this->json(['error' => 'Trop de tentatives, réessayez dans quelques minutes.'], 429);
        }

        $user = $this->userRepository->findOneBy(['email' => $dto->email]);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $dto->password)) {
            return $this->json(['error' => 'Identifiants invalides.'], 401);
        }

        $limiter->reset();

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
