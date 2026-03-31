<?php

namespace App\Service;

use App\Entity\Establishment;
use App\Entity\Review;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class AlertEmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
    ) {
    }

    /**
     * Envoie une alerte email quand un avis négatif (≤ 2) est détecté.
     */
    public function sendNegativeReviewAlert(Establishment $establishment, Review $review): void
    {
        $owner = $establishment->getOwner();

        if (!$owner->isAlertsEnabled() || !$establishment->isAlertsEnabled()) {
            return;
        }

        $html = $this->twig->render('emails/negative_review_alert.html.twig', [
            'establishment' => $establishment,
            'review' => $review,
        ]);

        $email = (new Email())
            ->from('noreply@monavispro.fr')
            ->to($owner->getEmail())
            ->subject(sprintf(
                '⚠️ Avis négatif — %s — %d/5 étoiles',
                $establishment->getName(),
                $review->getRating()
            ))
            ->html($html);

        $this->mailer->send($email);
    }
}
