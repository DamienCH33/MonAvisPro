<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Traduit une erreur de l'API Google en message lisible pour le commerçant,
 * et journalise la réponse brute de Google pour le diagnostic (logs Railway).
 */
class GoogleApiErrorDescriber
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param string $context ex. « callback OAuth », « publication réponse »
     */
    public function describe(\Throwable $e, string $context): string
    {
        [$status, $body] = $this->extractResponse($e);

        $this->logger->error('Google API [{context}] : {message}', [
            'context' => $context,
            'message' => $e->getMessage(),
            'status' => $status,
            'google_response' => $body,
            'exception_class' => $e::class,
        ]);

        $googleText = strtolower($body ?? '');

        return match (true) {
            $e instanceof TransportExceptionInterface && null === $status => 'Google ne répond pas pour le moment. Réessayez dans quelques minutes.',
            401 === $status, str_contains($googleText, 'invalid_grant') => 'Votre connexion Google a expiré. Reconnectez votre fiche depuis les réglages.',
            str_contains($googleText, 'service_disabled'), str_contains($googleText, 'has not been used in project') => 'L\'accès à Google Business Profile n\'est pas encore activé pour MonAvisPro. Contactez-nous.',
            429 === $status => 'Google limite temporairement les accès. Réessayez dans quelques minutes.',
            403 === $status => 'Google refuse l\'accès à cette fiche. Vérifiez que votre compte Google en est bien propriétaire ou gestionnaire.',
            404 === $status => 'Cet avis ou cette fiche n\'existe plus sur Google.',
            default => 'Une erreur est survenue avec Google. Réessayez dans quelques instants.',
        };
    }

    /**
     * @return array{0: int|null, 1: string|null}
     */
    private function extractResponse(\Throwable $e): array
    {
        if (!$e instanceof HttpExceptionInterface) {
            return [null, null];
        }

        $response = $e->getResponse();

        try {
            $body = $response->getContent(false);
        } catch (\Throwable) {
            $body = null;
        }

        return [$response->getStatusCode(), null !== $body ? mb_substr($body, 0, 2000) : null];
    }
}
