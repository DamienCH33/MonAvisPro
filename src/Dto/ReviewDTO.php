<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Review;

/**
 * Cette classe découple la structure exposée par l'API de l'entité Doctrine.
 */
final readonly class ReviewDTO
{
    public function __construct(
        public string $id,
        public ?string $googleAuthor,
        public ?string $googleAuthorPhoto,
        public int $rating,
        public ?string $text,
        public string $publishedAt,
        public bool $isRead,
        public ?string $googleReviewId,
        public ?string $ownerReply,
    ) {}

    public static function fromEntity(Review $review): self
    {
        $publishedAt = $review->getPublishedAt();

        return new self(
            id: (string) $review->getId(),
            googleAuthor: $review->getGoogleAuthor(),
            googleAuthorPhoto: $review->getGoogleAuthorPhoto(),
            rating: (int) $review->getRating(),
            text: $review->getText(),
            publishedAt: $publishedAt?->format('c') ?? '',
            isRead: $review->isRead(),
            googleReviewId: $review->getGoogleReviewId(),
            ownerReply: $review->getOwnerReply(),
        );
    }

    /**
     * @param iterable<Review> $reviews
     *
     * @return list<self>
     */
    public static function collection(iterable $reviews): array
    {
        $dtos = [];
        foreach ($reviews as $review) {
            $dtos[] = self::fromEntity($review);
        }

        return $dtos;
    }
}
