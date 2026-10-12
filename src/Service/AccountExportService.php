<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;

/**
 * Données du compte au format JSON (droit à la portabilité). Jamais de mot de passe ni de jeton Google.
 */
class AccountExportService
{
    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $establishments = [];

        foreach ($user->getEstablishments() as $establishment) {
            $reviews = [];
            foreach ($establishment->getReviews() as $review) {
                $reviews[] = [
                    'auteur' => $review->getGoogleAuthor(),
                    'note' => $review->getRating(),
                    'texte' => $review->getText(),
                    'publie_le' => $review->getPublishedAt()?->format(\DATE_ATOM),
                    'votre_reponse' => $review->getOwnerReply(),
                    'reponse_publiee_sur_google' => (bool) $review->isPublishedToGoogle(),
                    'ignore_le' => $review->getIgnoredAt()?->format(\DATE_ATOM),
                ];
            }

            $establishments[] = [
                'nom' => $establishment->getName(),
                'adresse' => $establishment->getAddress(),
                'cree_le' => $establishment->getCreatedAt()?->format(\DATE_ATOM),
                'relie_a_google' => $establishment->isConnectedToGoogleBusiness(),
                'reglages_de_reponse' => [
                    'ton' => $establishment->getReplyTone(),
                    'vouvoiement_ou_tutoiement' => $establishment->getReplyFormality(),
                    'signature' => $establishment->getReplySignature(),
                    'consignes' => $establishment->getReplyInstructions(),
                ],
                'email_du_bilan_mensuel' => $establishment->getClientEmail(),
                'avis' => $reviews,
            ];
        }

        return [
            'export' => [
                'service' => 'MonAvisPro',
                'genere_le' => (new \DateTimeImmutable())->format(\DATE_ATOM),
            ],
            'compte' => [
                'email' => $user->getEmail(),
                'cree_le' => $user->getCreatedAt()?->format(\DATE_ATOM),
                'alertes_avis_negatifs' => $user->isAlertsEnabled(),
            ],
            'etablissements' => $establishments,
        ];
    }
}
