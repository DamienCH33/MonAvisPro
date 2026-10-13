<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Establishment;
use App\Repository\EstablishmentRepository;
use Psr\Log\LoggerInterface;

/**
 * Retire l'accès de MonAvisPro au compte Google d'un établissement.
 *
 * Google révoque l'autorisation entière (compte Google × MonAvisPro) : si un autre
 * établissement du même client est relié au même compte Google, on ne révoque pas,
 * sinon sa synchronisation s'arrêterait aussi.
 */
class GoogleAccessRevoker
{
    public function __construct(
        private readonly EstablishmentRepository $establishmentRepository,
        private readonly GoogleBusinessProfileService $googleService,
        private readonly TokenCipher $cipher,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param bool $wholeAccount true quand tous les établissements du client disparaissent (suppression du compte)
     *
     * @return bool true si une révocation a été envoyée à Google
     */
    public function revokeFor(Establishment $establishment, bool $wholeAccount = false): bool
    {
        $token = $this->cipher->decrypt($establishment->getGoogleRefreshToken())
            ?? $this->cipher->decrypt($establishment->getGoogleAccessToken());

        if (null === $token || '' === $token) {
            return false;
        }

        if (!$wholeAccount && $this->sharedWithAnotherEstablishment($establishment)) {
            return false;
        }

        // Un jeton déjà révoqué ou expiré ne doit jamais bloquer la suppression.
        try {
            $this->googleService->revokeToken($token);

            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('Révocation de l\'accès Google impossible : {message}', [
                'message' => $e->getMessage(),
                'establishment' => (string) $establishment->getId(),
            ]);

            return false;
        }
    }

    private function sharedWithAnotherEstablishment(Establishment $establishment): bool
    {
        $accountId = $establishment->getGoogleAccountId();
        if (null === $accountId) {
            return false;
        }

        foreach ($this->establishmentRepository->findBy(['owner' => $establishment->getOwner(), 'googleAccountId' => $accountId]) as $other) {
            if ($other !== $establishment && $other->isConnectedToGoogleBusiness()) {
                return true;
            }
        }

        return false;
    }
}
