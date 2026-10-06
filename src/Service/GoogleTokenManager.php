<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Establishment;

/**
 * Garde le jeton d'accès Google Business Profile d'un établissement valide.
 */
class GoogleTokenManager
{
    public function __construct(
        private readonly GoogleBusinessProfileService $googleService,
    ) {
    }

    /**
     * Rafraîchit le jeton s'il a expiré (ou expire dans la minute) et le renvoie.
     * Ne fait pas de flush : l'appelant enregistre l'établissement.
     */
    public function getValidAccessToken(Establishment $establishment): ?string
    {
        $accessToken = $establishment->getGoogleAccessToken();
        if (null === $accessToken) {
            return null;
        }

        $expiresAt = $establishment->getGoogleTokenExpiresAt();
        $soon = new \DateTimeImmutable('+60 seconds');

        if (null !== $expiresAt && $expiresAt > $soon) {
            return $accessToken;
        }

        $refreshToken = $establishment->getGoogleRefreshToken();
        if (null === $refreshToken) {
            return $accessToken;
        }

        $tokenData = $this->googleService->refreshAccessToken($refreshToken);
        $newToken = $tokenData['access_token'] ?? null;

        if (!is_string($newToken) || '' === $newToken) {
            return $accessToken;
        }

        $expiresIn = (int) ($tokenData['expires_in'] ?? 3600);
        $establishment->setGoogleAccessToken($newToken);
        $establishment->setGoogleTokenExpiresAt(new \DateTimeImmutable('+'.$expiresIn.' seconds'));

        return $newToken;
    }
}
