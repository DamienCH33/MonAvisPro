<?php

declare(strict_types=1);

namespace App\Dto\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Contrat d'entrée pour la création d'un compte utilisateur.
 */
final readonly class RegisterRequestDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email et mot de passe requis.')]
        #[Assert\Email(message: 'Format d\'email invalide.')]
        public string $email,

        #[Assert\NotBlank(message: 'Email et mot de passe requis.')]
        #[Assert\Length(
            min: 8,
            minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.'
        )]
        public string $password,

        public bool $alertsEnabled = true,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new self(email: '', password: '');
        }

        return new self(
            email: trim((string) ($data['email'] ?? '')),
            password: (string) ($data['password'] ?? ''),
            alertsEnabled: (bool) ($data['alertsEnabled'] ?? true),
        );
    }
}
