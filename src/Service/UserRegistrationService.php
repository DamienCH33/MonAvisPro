<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\Request\RegisterRequestDTO;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Service responsable de la création d'un compte utilisateur.
 */
class UserRegistrationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    /**
     * @throws EmailAlreadyUsedException si un compte existe déjà avec cet email
     */
    public function register(RegisterRequestDTO $dto): User
    {
        if ($this->userRepository->findOneBy(['email' => $dto->email])) {
            throw new EmailAlreadyUsedException('Cet email est déjà utilisé.');
        }

        $user = new User();
        $user->setEmail($dto->email);
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $dto->password)
        );
        $user->setAlertsEnabled($dto->alertsEnabled);

        if (!$user->getCreatedAt()) {
            $user->setCreatedAt(new \DateTimeImmutable());
        }

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * @throws EmailAlreadyUsedException si un compte existe déjà avec cet email
     */
    public function registerFromForm(User $user, string $plainPassword): User
    {
        if ($this->userRepository->findOneBy(['email' => $user->getEmail()])) {
            throw new EmailAlreadyUsedException('Cet email est déjà utilisé.');
        }

        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $plainPassword)
        );

        if (!$user->getCreatedAt()) {
            $user->setCreatedAt(new \DateTimeImmutable());
        }

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
