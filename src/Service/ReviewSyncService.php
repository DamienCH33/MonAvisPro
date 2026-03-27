<?php

namespace App\Service;

use App\Entity\Establishment;
use App\Entity\Review;
use App\Service\AlertEmailService;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;

class ReviewSyncService
{
    public function __construct(
        private GooglePlacesService $googlePlacesService,
        private EntityManagerInterface $em,
        private ReviewRepository $reviewRepository,
        private AlertEmailService $alertEmailService,
    ) {}

    /**
     * Synchronise les avis Google d'un établissement
     * Retourne le nombre de nouveaux avis insérés
     */
    public function sync(Establishment $establishment): int
    {
        $data = $this->googlePlacesService->getPlaceDetails($establishment->getPlaceId());

        if ($data === null || empty($data['reviews'])) {
            return 0;
        }

        $existingReviews = $this->reviewRepository->findBy([
            'establishment' => $establishment
        ]);

        $existingIds = array_map(
            fn(Review $review) => $review->getGoogleReviewId(),
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

            $review = new Review();
            $review->setEstablishment($establishment);
            $review->setGoogleReviewId($reviewData['googleReviewId']);
            $review->setGoogleAuthor($reviewData['googleAuthor'] ?? null);
            $review->setGoogleAuthorPhoto($reviewData['googleAuthorPhoto'] ?? null);
            $review->setRating($reviewData['rating'] ?? 0);
            $review->setText($reviewData['text'] ?? '');
            $review->setPublishedAt($reviewData['publishedAt'] ?? new \DateTimeImmutable());
            $review->setIsRead(false);

            $this->em->persist($review);
            $newCount++;

            if (($reviewData['rating'] ?? 5) <= 2 && $establishment->isAlertsEnabled()) {
                $this->alertEmailService->sendNegativeReviewAlert($establishment, $review);
            }
        }

        $establishment->setLastSyncAt(new \DateTimeImmutable());
        $this->em->flush();

        return $newCount;
    }
}
