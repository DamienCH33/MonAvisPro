<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Establishment;
use App\Entity\User;
use App\Repository\EstablishmentRepository;
use App\Repository\ReviewRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Barre latérale : nombre d'avis à traiter (badge) et établissements de l'utilisateur.
 */
final class InboxExtension extends AbstractExtension
{
    private ?int $count = null;

    public function __construct(
        private readonly ReviewRepository $reviewRepository,
        private readonly EstablishmentRepository $establishmentRepository,
        private readonly Security $security,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('inbox_count', $this->inboxCount(...)),
            new TwigFunction('owner_establishments', $this->ownerEstablishments(...)),
        ];
    }

    public function inboxCount(): int
    {
        if (null !== $this->count) {
            return $this->count;
        }

        $user = $this->security->getUser();

        return $this->count = $user instanceof User ? $this->reviewRepository->countUnansweredForOwner($user) : 0;
    }

    /**
     * Établissements de l'utilisateur connecté, pour la barre latérale des pages
     * qui ne les fournissent pas (pages légales vues connecté).
     *
     * @return array<int, Establishment>
     */
    public function ownerEstablishments(): array
    {
        $user = $this->security->getUser();

        return $user instanceof User
            ? $this->establishmentRepository->findBy(['owner' => $user], ['name' => 'ASC'])
            : [];
    }
}
