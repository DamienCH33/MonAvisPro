<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;

/**
 * Contrat d'API pour un utilisateur.
 */
final readonly class UserDTO
{
    public function __construct(
        public string $id,
        public string $email,
        public bool $alertsEnabled,
        public string $createdAt,
    ) {}

    public static function fromEntity(User $user): self
    {
        $createdAt = $user->getCreatedAt();

        return new self(
            id: $user->getId()?->toRfc4122() ?? '',
            email: (string) $user->getEmail(),
            alertsEnabled: $user->isAlertsEnabled(),
            createdAt: $createdAt?->format('c') ?? '',
        );
    }

    /**
     * @param iterable<User> $users
     *
     * @return list<self>
     */
    public static function collection(iterable $users): array
    {
        $dtos = [];
        foreach ($users as $user) {
            $dtos[] = self::fromEntity($user);
        }

        return $dtos;
    }
}
