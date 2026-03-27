<?php

namespace App\Trait;

use App\Entity\Review;

trait ReviewSerializerTrait
{
    private function serialize(Review $r): array
    {
        return [
            'id'                => $r->getId(),
            'googleAuthor'      => $r->getGoogleAuthor(),
            'googleAuthorPhoto' => $r->getGoogleAuthorPhoto(),
            'rating'            => $r->getRating(),
            'text'              => $r->getText(),
            'publishedAt'       => $r->getPublishedAt()->format('c'),
            'isRead'            => $r->isRead(),
            'googleReviewId'    => $r->getGoogleReviewId(),
        ];
    }
}
