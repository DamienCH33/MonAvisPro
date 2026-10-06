<?php

namespace App\Service;

use App\Entity\Establishment;
use App\Entity\Review;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;

class ReviewSyncService
{
    private const STAR_RATINGS = ['ONE' => 1, 'TWO' => 2, 'THREE' => 3, 'FOUR' => 4, 'FIVE' => 5];
    private const MAX_PAGES = 20;

    public function __construct(
        private GooglePlacesService $googlePlacesService,
        private EntityManagerInterface $em,
        private ReviewRepository $reviewRepository,
        private AlertEmailService $alertEmailService,
        private GoogleBusinessProfileService $googleBusinessService,
        private GoogleTokenManager $tokenManager,
    ) {
    }

    /**
     * Synchronise les avis Google d'un établissement.
     * Relié à Google Business : tous les avis, avec les réponses existantes.
     * Sinon : les avis renvoyés par Google Places (5 maximum, lecture seule).
     * Retourne le nombre de nouveaux avis insérés.
     */
    public function sync(Establishment $establishment): int
    {
        if ($establishment->isConnectedToGoogleBusiness()) {
            return $this->syncFromBusinessProfile($establishment);
        }

        return $this->syncFromPlaces($establishment);
    }

    private function syncFromBusinessProfile(Establishment $establishment): int
    {
        $accessToken = (string) $this->tokenManager->getValidAccessToken($establishment);
        $existing = $this->indexExistingReviews($establishment);

        $newCount = 0;
        $pageToken = null;
        $page = 0;

        do {
            $data = $this->googleBusinessService->listReviews(
                (string) $establishment->getGoogleAccountId(),
                (string) $establishment->getGoogleLocationId(),
                $accessToken,
                $pageToken,
            );

            foreach ($data['reviews'] ?? [] as $reviewData) {
                $reviewId = $reviewData['reviewId'] ?? null;
                $rating = self::STAR_RATINGS[$reviewData['starRating'] ?? ''] ?? null;

                if (!is_string($reviewId) || '' === $reviewId || null === $rating) {
                    continue;
                }

                $googleReply = $reviewData['reviewReply']['comment'] ?? null;
                $googleReply = is_string($googleReply) && '' !== trim($googleReply) ? $googleReply : null;

                if (isset($existing[$reviewId])) {
                    $this->refreshExistingReview($existing[$reviewId], $reviewData, $googleReply);
                    continue;
                }

                $review = new Review();
                $review->setEstablishment($establishment);
                $review->setGoogleReviewId($reviewId);
                $review->setGoogleReviewName(is_string($reviewData['name'] ?? null) ? $reviewData['name'] : null);
                $review->setGoogleAuthor($this->authorName($reviewData));
                $review->setGoogleAuthorPhoto($this->safePhotoUrl($reviewData['reviewer']['profilePhotoUrl'] ?? null));
                $review->setRating($rating);
                $review->setText($this->originalText($reviewData['comment'] ?? null));
                $review->setPublishedAt($this->parseDate($reviewData['createTime'] ?? null));
                $review->setIsRead(false);

                if (null !== $googleReply) {
                    $review->setOwnerReply($googleReply);
                    $review->setIsPublishedToGoogle(true);
                    $review->setGoogleReplyPublishedAt($this->parseDate($reviewData['reviewReply']['updateTime'] ?? null));
                }

                $this->em->persist($review);
                $existing[$reviewId] = $review;
                ++$newCount;

                if ($rating <= 2 && null === $googleReply && $establishment->isAlertsEnabled()) {
                    $this->alertEmailService->sendNegativeReviewAlert($establishment, $review);
                }
            }

            $pageToken = $data['nextPageToken'] ?? null;
            ++$page;
        } while (is_string($pageToken) && '' !== $pageToken && $page < self::MAX_PAGES);

        $establishment->setLastSyncAt(new \DateTimeImmutable());
        $this->em->flush();

        return $newCount;
    }

    private function syncFromPlaces(Establishment $establishment): int
    {
        $placeId = $establishment->getPlaceId();

        if (null === $placeId) {
            return 0;
        }

        $data = $this->googlePlacesService->getPlaceDetails($placeId);

        if (null === $data || empty($data['reviews'])) {
            return 0;
        }

        $existingIds = array_keys($this->indexExistingReviews($establishment));

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
            $review->setGoogleAuthorPhoto($this->safePhotoUrl($reviewData['googleAuthorPhoto']));
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

    /**
     * @return array<string, Review> avis existants indexés par identifiant Google
     */
    private function indexExistingReviews(Establishment $establishment): array
    {
        $index = [];
        foreach ($this->reviewRepository->findBy(['establishment' => $establishment]) as $review) {
            $id = $review->getGoogleReviewId();
            if (null !== $id) {
                $index[$id] = $review;
            }
        }

        return $index;
    }

    /**
     * Met à jour un avis déjà connu : nom Google (pour publier) et réponse faite ailleurs.
     *
     * @param array<string, mixed> $reviewData
     */
    private function refreshExistingReview(Review $review, array $reviewData, ?string $googleReply): void
    {
        if (null === $review->getGoogleReviewName() && is_string($reviewData['name'] ?? null)) {
            $review->setGoogleReviewName($reviewData['name']);
        }

        // Réponse publiée directement sur Google (par le commerçant, par exemple).
        if (null !== $googleReply && null === $review->getOwnerReply()) {
            $review->setOwnerReply($googleReply);
            $review->setIsPublishedToGoogle(true);
        }
    }

    /** Photo de profil : uniquement une URL https (jamais javascript:, data:…). */
    private function safePhotoUrl(mixed $url): ?string
    {
        if (!is_string($url) || mb_strlen($url) > 500 || 'https' !== parse_url($url, PHP_URL_SCHEME)) {
            return null;
        }

        return $url;
    }

    /** @param array<string, mixed> $reviewData */
    private function authorName(array $reviewData): string
    {
        $name = $reviewData['reviewer']['displayName'] ?? null;
        $anonymous = (bool) ($reviewData['reviewer']['isAnonymous'] ?? false);

        if ($anonymous || !is_string($name) || '' === trim($name)) {
            return 'Client anonyme';
        }

        return $name;
    }

    /**
     * Google ajoute parfois une traduction automatique : on garde le texte d'origine.
     */
    private function originalText(mixed $comment): string
    {
        if (!is_string($comment)) {
            return '';
        }

        if (false !== ($pos = strpos($comment, '(Original)'))) {
            return trim(substr($comment, $pos + strlen('(Original)')));
        }

        if (false !== ($pos = strpos($comment, '(Translated by Google)'))) {
            return trim(substr($comment, 0, $pos));
        }

        return trim($comment);
    }

    private function parseDate(mixed $value): \DateTimeImmutable
    {
        if (is_string($value)) {
            try {
                // Google renvoie jusqu'à 9 décimales : on les tronque à 6.
                $value = (string) preg_replace('/\.(\d{6})\d+/', '.$1', $value);

                return new \DateTimeImmutable($value);
            } catch (\Exception) {
            }
        }

        return new \DateTimeImmutable();
    }
}
