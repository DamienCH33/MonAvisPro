<?php

namespace App\Tests\Unit\Service;

use App\Entity\Establishment;
use App\Entity\Review;
use App\Repository\ReviewRepository;
use App\Service\AlertEmailService;
use App\Service\GooglePlacesService;
use App\Service\ReviewSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ReviewSyncServiceTest extends TestCase
{
    public function testNoDuplicatesInserted(): void
    {
        $establishment = new Establishment();
        $establishment->setPlaceId('ChIJtest123');

        $googleData = [
            'reviews' => [
                [
                    'googleReviewId' => 'existing-review-id',
                    'googleAuthor' => 'Jean Dupont',
                    'googleAuthorPhoto' => null,
                    'rating' => 4,
                    'text' => 'Super !',
                    'publishedAt' => new \DateTimeImmutable(),
                ],
            ],
        ];

        $googleService = $this->createMock(GooglePlacesService::class);
        $googleService->method('getPlaceDetails')->willReturn($googleData);

        $existingReview = new Review();
        $existingReview->setGoogleReviewId('existing-review-id');

        $reviewRepository = $this->createMock(ReviewRepository::class);
        $reviewRepository->method('findBy')->willReturn([$existingReview]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $alertService = $this->createMock(AlertEmailService::class);

        $service = new ReviewSyncService($googleService, $em, $reviewRepository, $alertService);
        $count = $service->sync($establishment);

        $this->assertSame(0, $count);
    }

    public function testNewReviewIsInserted(): void
    {
        $establishment = new Establishment();
        $establishment->setPlaceId('ChIJtest123');

        $googleData = [
            'reviews' => [
                [
                    'googleReviewId' => 'new-review-id',
                    'googleAuthor' => 'Marie Martin',
                    'googleAuthorPhoto' => null,
                    'rating' => 5,
                    'text' => 'Excellent !',
                    'publishedAt' => new \DateTimeImmutable(),
                ],
            ],
        ];

        $googleService = $this->createMock(GooglePlacesService::class);
        $googleService->method('getPlaceDetails')->willReturn($googleData);

        $reviewRepository = $this->createMock(ReviewRepository::class);
        $reviewRepository->method('findBy')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $alertService = $this->createMock(AlertEmailService::class);

        $service = new ReviewSyncService($googleService, $em, $reviewRepository, $alertService);
        $count = $service->sync($establishment);

        $this->assertSame(1, $count);
    }
}
