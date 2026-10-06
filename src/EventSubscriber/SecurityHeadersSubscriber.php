<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ajoute les en-têtes de sécurité HTTP à toutes les réponses.
 */
final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    private const CSP = "default-src 'self'; "
        ."script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; "
        ."style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; "
        ."font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:; "
        ."img-src 'self' data: https:; "
        ."connect-src 'self'; "
        ."frame-ancestors 'none'; base-uri 'self'; object-src 'none'; "
        ."form-action 'self' https://accounts.google.com";

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onResponse', -10]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if (!$headers->has('Content-Security-Policy')) {
            $headers->set('Content-Security-Policy', self::CSP);
        }

        if ($event->getRequest()->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $headers->remove('X-Powered-By');
    }
}
