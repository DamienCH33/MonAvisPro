<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Establishment;

/**
 * Garde le jeton d'accès Google Business Profile d'un établissement valide.
 * Les jetons sont stockés chiffrés en base (TokenCipher).
 */
class GoogleTokenManager
{
    public function __construct(
        private readonly GoogleBusinessProfileService $googleService,
        private readonly TokenCipher $cipher,
    ) {
    }

    /**
     * Enregistre les jetons reçus de Google sur l'établissement (chiffrés).
     */
    public function storeTokens(Establishment $establishment, string $accessToken, ?string $refreshToken, int $expiresAtTimestamp): void
    {
        $establishment->setGoogleAccessToken($this->cipher->encrypt($accessToken));
        if (null !== $refreshToken && '' !== $refreshToken) {
            $establishment->setGoogleRefreshToken($this->cipher->encrypt($refreshToken));
        }
        $establishment->setGoogleTokenExpiresAt((new \DateTimeImmutable())->setTimestamp($expiresAtTimestamp));
    }

    /**
     * Rafraîchit le jeton s'il a expiré (ou expire dans la minute) et le renvoie en clair.
     * Ne fait pas de flush : l'appelant enregistre l'établissement.
     */
    public function getValidAccessToken(Establishment $establishment): ?string
    {
        $accessToken = $this->cipher->decrypt($establishment->getGoogleAccessToken());
        if (null === $accessToken) {
            return null;
        }

        $expiresAt = $establishment->getGoogleTokenExpiresAt();
        if (null !== $expiresAt && $expiresAt > new \DateTimeImmutable('+60 seconds')) {
            return $accessToken;
        }

        $refreshToken = $this->cipher->decrypt($establishment->getGoogleRefreshToken());
        if (null === $refreshToken) {
            return $accessToken;
        }

        $tokenData = $this->googleService->refreshAccessToken($refreshToken);
        $newToken = $tokenData['access_token'] ?? null;

        if (!is_string($newToken) || '' === $newToken) {
            return $accessToken;
        }

        $expiresIn = (int) ($tokenData['expires_in'] ?? 3600);
        $establishment->setGoogleAccessToken($this->cipher->encrypt($newToken));
        $establishment->setGoogleTokenExpiresAt(new \DateTimeImmutable('+'.$expiresIn.' seconds'));

        // Si Google en profite pour renouveler le jeton de rafraîchissement aussi, on le garde chiffré.
        if (is_string($tokenData['refresh_token'] ?? null) && '' !== $tokenData['refresh_token']) {
            $establishment->setGoogleRefreshToken($this->cipher->encrypt($tokenData['refresh_token']));
        }

        return $newToken;
    }
}
