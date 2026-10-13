<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Protection CSRF de l'API depuis qu'elle accepte la session du site.
 *
 * Une requête qui modifie quelque chose (POST, PUT, PATCH, DELETE) doit soit porter
 * un jeton JWT (client externe), soit l'en-tête X-Requested-With envoyé par le JS
 * du site. Un autre site ne peut pas ajouter cet en-tête à une requête vers
 * MonAvisPro sans autorisation CORS, que l'API ne donne pas.
 */
final class ApiCsrfGuardSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        // Avant le pare-feu (priorité 8) : une requête refusée n'est jamais authentifiée.
        return [KernelEvents::REQUEST => ['onRequest', 16]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/api/') || str_starts_with($path, '/api/auth/') || $request->isMethodSafe()) {
            return;
        }

        if (str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ')) {
            return;
        }

        if ('XMLHttpRequest' === $request->headers->get('X-Requested-With')) {
            return;
        }

        $event->setResponse(new JsonResponse([
            'error' => 'Requête refusée. Rechargez la page et réessayez.',
            'status' => 403,
        ], 403));
    }
}
