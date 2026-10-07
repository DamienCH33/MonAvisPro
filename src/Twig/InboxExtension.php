<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Repository\ReviewRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Nombre d'avis sans réponse, tous établissements confondus (badge « À traiter »).
 */
final class InboxExtension extends AbstractExtension
{
    private ?int $count = null;

    public function __construct(
        private readonly ReviewRepository $reviewRepository,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('inbox_count', $this->inboxCount(...))];
    }

    public function inboxCount(): int
    {
        if (null !== $this->count) {
            return $this->count;
        }

        $user = $this->security->getUser();

        return $this->count = $user instanceof User ? $this->reviewRepository->countUnansweredForOwner($user) : 0;
    }
}
