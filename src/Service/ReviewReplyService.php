<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Establishment;
use App\Entity\Review;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Orchestre la publication, la mise à jour et la suppression de réponses
 * sur les avis Google.
 */
class ReviewReplyService
{
    public function __construct(
        private readonly GoogleBusinessProfileService $googleService,
        private readonly EntityManagerInterface $em,
        private readonly GoogleTokenManager $tokenManager,
    ) {
    }

    /**
     * Sauvegarde la réponse localement et la publie sur Google si possible.
     *
     * @return array{success: true, warning?: string}
     */
    public function publish(Review $review, string $replyText): array
    {
        $review->setOwnerReply($replyText);

        if (!$this->canInteractWithGoogle($review)) {
            $this->em->flush();

            return [
                'success' => true,
                'warning' => 'Réponse enregistrée dans MonAvisPro, mais pas publiée : cet établissement n\'est pas relié à Google Business. Copiez-la et publiez-la depuis la fiche Google.',
            ];
        }

        try {
            /** @var Establishment $establishment checked by canInteractWithGoogle() */
            $establishment = $review->getEstablishment();
            $accessToken = $this->tokenManager->getValidAccessToken($establishment);

            $this->googleService->publishReply(
                (string) $review->getGoogleReviewName(),
                $replyText,
                (string) $accessToken,
            );

            $review->setIsPublishedToGoogle(true);
            $review->setGoogleReplyPublishedAt(new \DateTimeImmutable());
            $this->em->flush();

            return ['success' => true];
        } catch (\Exception $e) {
            $this->em->flush();

            return [
                'success' => true,
                'warning' => 'Réponse sauvegardée localement mais non publiée sur Google : '.$e->getMessage(),
            ];
        }
    }

    /**
     * Supprime la réponse localement et sur Google si elle y était publiée.
     *
     * @return array{success: true, warning?: string}
     */
    public function delete(Review $review): array
    {
        $shouldDeleteOnGoogle = $review->isPublishedToGoogle()
            && $this->canInteractWithGoogle($review);

        $warning = null;

        if ($shouldDeleteOnGoogle) {
            try {
                /** @var Establishment $establishment checked by canInteractWithGoogle() */
                $establishment = $review->getEstablishment();
                $accessToken = $this->tokenManager->getValidAccessToken($establishment);

                $this->googleService->deleteReply(
                    (string) $review->getGoogleReviewName(),
                    (string) $accessToken,
                );
            } catch (\Exception $e) {
                $warning = 'Réponse supprimée localement mais erreur Google : '.$e->getMessage();
            }
        }

        $review->setOwnerReply(null);
        $review->setIsPublishedToGoogle(false);
        $review->setGoogleReplyPublishedAt(null);
        $this->em->flush();

        return null !== $warning
            ? ['success' => true, 'warning' => $warning]
            : ['success' => true];
    }

    /**
     * Vérifie qu'un avis est éligible à un échange avec l'API Google.
     */
    private function canInteractWithGoogle(Review $review): bool
    {
        $establishment = $review->getEstablishment();

        return null !== $establishment
            && null !== $establishment->getGoogleAccessToken()
            && null !== $review->getGoogleReviewName();
    }
}
