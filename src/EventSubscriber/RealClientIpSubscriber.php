<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Vraie IP du visiteur derrière Railway.
 *
 * Railway relaie les requêtes par plusieurs serveurs : l'IP la plus à droite de
 * X-Forwarded-For est celle d'un relais, qui change d'une requête à l'autre, et ce
 * qui est à gauche peut être inventé par le visiteur. Les limiteurs de débit
 * (connexion, inscription, IA) ne bloquaient donc jamais personne.
 *
 * Railway pose X-Real-IP avec l'IP réelle du visiteur. Quand la requête vient bien
 * du proxy de confiance, on la reprend comme seule valeur de X-Forwarded-For :
 * getClientIp() renvoie alors l'IP réelle partout (limiteurs, journaux).
 */
final class RealClientIpSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly string $header = 'X-Real-IP',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Avant tout le reste (pare-feu, limiteurs), qui lit l'IP du client.
        return [KernelEvents::REQUEST => ['onRequest', 4096]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || '' === $this->header) {
            return;
        }

        $request = $event->getRequest();
        $realIp = trim((string) $request->headers->get($this->header));

        if ('' === $realIp || false === filter_var($realIp, FILTER_VALIDATE_IP) || !$request->isFromTrustedProxy()) {
            return;
        }

        $request->headers->set('X-Forwarded-For', $realIp);
    }
}
