<?php

namespace App\Tests\Unit\Service;

use App\Entity\Establishment;
use App\Entity\Review;
use App\Repository\ReviewRepository;
use App\Service\AlertEmailService;
use App\Service\GoogleBusinessProfileService;
use App\Service\GooglePlacesService;
use App\Service\GoogleTokenManager;
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

        $service = new ReviewSyncService(
            $googleService,
            $em,
            $reviewRepository,
            $alertService,
            $this->createStub(GoogleBusinessProfileService::class),
            $this->createStub(GoogleTokenManager::class),
        );
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

        $service = new ReviewSyncService(
            $googleService,
            $em,
            $reviewRepository,
            $alertService,
            $this->createStub(GoogleBusinessProfileService::class),
            $this->createStub(GoogleTokenManager::class),
        );
        $count = $service->sync($establishment);

        $this->assertSame(1, $count);
    }

    public function testBusinessProfileSyncImportsAllReviewsAndExistingReplies(): void
    {
        $establishment = new Establishment();
        $establishment->setPlaceId('ChIJtest123');
        $establishment->setGoogleAccountId('accounts/1');
        $establishment->setGoogleLocationId('locations/2');
        $establishment->setGoogleAccessToken('token');
        $establishment->setGoogleTokenExpiresAt(new \DateTimeImmutable('+1 hour'));

        $page1 = [
            'reviews' => [
                [
                    'name' => 'accounts/1/locations/2/reviews/r1',
                    'reviewId' => 'r1',
                    'reviewer' => ['displayName' => 'Marie Martin', 'profilePhotoUrl' => 'https://example.test/m.png'],
                    'starRating' => 'FIVE',
                    'comment' => 'Pain délicieux',
                    'createTime' => '2026-09-01T10:00:00.123456789Z',
                    'reviewReply' => ['comment' => 'Merci Marie !', 'updateTime' => '2026-09-02T08:00:00Z'],
                ],
            ],
            'nextPageToken' => 'page-2',
        ];
        $page2 = [
            'reviews' => [
                [
                    'name' => 'accounts/1/locations/2/reviews/r2',
                    'reviewId' => 'r2',
                    'reviewer' => ['isAnonymous' => true],
                    'starRating' => 'TWO',
                    'comment' => "(Translated by Google) Too long\n\n(Original)\nTrop d'attente",
                    'createTime' => '2026-09-03T10:00:00Z',
                ],
            ],
        ];

        $businessService = $this->createMock(GoogleBusinessProfileService::class);
        $businessService->expects($this->exactly(2))
            ->method('listReviews')
            ->willReturnOnConsecutiveCalls($page1, $page2);

        $tokenManager = $this->createStub(GoogleTokenManager::class);
        $tokenManager->method('getValidAccessToken')->willReturn('token');

        $placesService = $this->createMock(GooglePlacesService::class);
        $placesService->expects($this->never())->method('getPlaceDetails');

        $reviewRepository = $this->createStub(ReviewRepository::class);
        $reviewRepository->method('findBy')->willReturn([]);

        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $alertService = $this->createMock(AlertEmailService::class);
        $alertService->expects($this->once())->method('sendNegativeReviewAlert');

        $service = new ReviewSyncService($placesService, $em, $reviewRepository, $alertService, $businessService, $tokenManager);

        $this->assertSame(2, $service->sync($establishment));

        /** @var Review $answered */
        $answered = $persisted[0];
        $this->assertSame('accounts/1/locations/2/reviews/r1', $answered->getGoogleReviewName());
        $this->assertSame(5, $answered->getRating());
        $this->assertSame('Merci Marie !', $answered->getOwnerReply());
        $this->assertTrue($answered->isPublishedToGoogle());

        /** @var Review $unanswered */
        $unanswered = $persisted[1];
        $this->assertSame('Client anonyme', $unanswered->getGoogleAuthor());
        $this->assertSame("Trop d'attente", $unanswered->getText());
        $this->assertNull($unanswered->getOwnerReply());
    }
}
