<?php

namespace App\Service;

use App\Entity\Establishment;
use App\Entity\Review;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;

class ReviewSyncService
{
    public function __construct(
        private GooglePlacesService $googlePlacesService,
        private EntityManagerInterface $em,
        private ReviewRepository $reviewRepository,
        private AlertEmailService $alertEmailService,
    ) {
    }

    /**
     * Synchronise les avis Google d'un établissement
     * Retourne le nombre de nouveaux avis insérés.
     */
    public function sync(Establishment $establishment): int
    {
        $placeId = $establishment->getPlaceId();

        if (null === $placeId) {
            return 0;
        }

        $data = $this->googlePlacesService->getPlaceDetails($placeId);

        if (null === $data || empty($data['reviews'])) {
            return 0;
        }

        $existingReviews = $this->reviewRepository->findBy([
            'establishment' => $establishment,
        ]);

        $existingIds = array_map(
            static fn (Review $review): ?string => $review->getGoogleReviewId(),
            $existingReviews
        );

        $newCount = 0;

        foreach ($data['reviews'] as $reviewData) {
            if (empty($reviewData['googleReviewId'])) {
                continue;
            }

            if (in_array($reviewData['googleReviewId'], $existingIds, true)) {
                continue;
            }

            $rating = (int) round($reviewData['rating']);

            $review = new Review();
            $review->setEstablishment($establishment);
            $review->setGoogleReviewId($reviewData['googleReviewId']);
            $review->setGoogleAuthor($reviewData['googleAuthor']);
            $review->setGoogleAuthorPhoto($reviewData['googleAuthorPhoto']);
            $review->setRating($rating);
            $review->setText($reviewData['text'] ?? '');
            $review->setPublishedAt($reviewData['publishedAt']);
            $review->setIsRead(false);

            $this->em->persist($review);
            ++$newCount;

            if ($rating <= 2 && $establishment->isAlertsEnabled()) {
                $this->alertEmailService->sendNegativeReviewAlert(
                    $establishment,
                    $review
                );
            }
        }

        $establishment->setLastSyncAt(new \DateTimeImmutable());
        $this->em->flush();

        return $newCount;
    }
}
