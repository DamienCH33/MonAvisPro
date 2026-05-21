<?php

declare(strict_types=1);

namespace App\Dto\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Contrat d'entrée pour la connexion d'un utilisateur.
 */
final readonly class LoginRequestDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email et mot de passe requis.')]
        public string $email,

        #[Assert\NotBlank(message: 'Email et mot de passe requis.')]
        public string $password,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return new self(email: '', password: '');
        }

        return new self(
            email: trim((string) ($data['email'] ?? '')),
            password: (string) ($data['password'] ?? ''),
        );
    }
}
