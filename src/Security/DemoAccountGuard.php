<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;

/**
 * Le compte démo est public (bouton « Essayer la démo ») : on limite ce qu'il peut faire.
 */
final class DemoAccountGuard
{
    public const DEMO_EMAIL = 'demo@monavispro.fr';

    public function isDemo(mixed $user): bool
    {
        return $user instanceof User && self::DEMO_EMAIL === $user->getEmail();
    }
}
