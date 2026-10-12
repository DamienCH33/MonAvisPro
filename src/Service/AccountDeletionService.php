<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\EstablishmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Suppression définitive d'un compte : accès Google retiré, puis tout effacé
 * en base (établissements, avis, analyses, demandes de mot de passe : ON DELETE CASCADE).
 */
class AccountDeletionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly EstablishmentRepository $establishmentRepository,
        private readonly GoogleBusinessProfileService $googleService,
        private readonly TokenCipher $cipher,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function delete(User $user): void
    {
        foreach ($this->establishmentRepository->findBy(['owner' => $user]) as $establishment) {
            $token = $this->cipher->decrypt($establishment->getGoogleRefreshToken())
                ?? $this->cipher->decrypt($establishment->getGoogleAccessToken());

            if (null === $token) {
                continue;
            }

            // Un jeton déjà révoqué ou expiré ne doit pas empêcher la suppression.
            try {
                $this->googleService->revokeToken($token);
            } catch (\Throwable $e) {
                $this->logger->warning('Révocation Google impossible lors de la suppression du compte : {message}', [
                    'message' => $e->getMessage(),
                    'establishment' => (string) $establishment->getId(),
                ]);
            }
        }

        $this->em->getConnection()->executeStatement(
            'DELETE FROM "user" WHERE id = :id',
            ['id' => (string) $user->getId()],
        );
        $this->em->clear();
    }
}
