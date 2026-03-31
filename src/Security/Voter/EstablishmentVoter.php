<?php

namespace App\Security\Voter;

use App\Entity\Establishment;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Establishment>
 */
class EstablishmentVoter extends Voter
{
    public const OWNER = 'ESTABLISHMENT_OWNER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::OWNER === $attribute && $subject instanceof Establishment;
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
    ): bool {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        return $subject->getOwner() === $user;
    }
}
