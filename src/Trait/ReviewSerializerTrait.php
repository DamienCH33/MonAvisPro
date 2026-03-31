<?php

namespace App\Trait;

use App\Entity\Review;

trait ReviewSerializerTrait
{
    /**
     * @return array{
     *     id: mixed,
     *     googleAuthor: string|null,
     *     googleAuthorPhoto: string|null,
     *     rating: int,
     *     text: string|null,
     *     publishedAt: string,
     *     isRead: bool,
     *     googleReviewId: string|null
     * }
     */
    private function serialize(Review $r): array
    {
        return [
            'id' => $r->getId(),
            'googleAuthor' => $r->getGoogleAuthor(),
            'googleAuthorPhoto' => $r->getGoogleAuthorPhoto(),
            'rating' => $r->getRating(),
            'text' => $r->getText(),
            'publishedAt' => $r->getPublishedAt()->format('c'),
            'isRead' => $r->isRead(),
            'googleReviewId' => $r->getGoogleReviewId(),
        ];
    }
}
