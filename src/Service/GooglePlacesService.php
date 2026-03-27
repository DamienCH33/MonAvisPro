<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GooglePlacesService
{
    private const BASE_URL = 'https://places.googleapis.com/v1/places/';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
    ) {}

    /**
     * Récupère les détails + avis d'un établissement via son Google Place ID
     */
    public function getPlaceDetails(string $placeId): ?array
    {
        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . $placeId, [
                'headers' => [
                    'X-Goog-Api-Key'    => $this->apiKey,
                    'X-Goog-FieldMask'  => 'id,displayName,formattedAddress,rating,reviews',
                ],
            ]);

            $data = $response->toArray();

            return $this->format($data);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Formate la réponse brute Google en tableau propre
     */
    private function format(array $data): array
    {
        $reviews = [];

        foreach ($data['reviews'] ?? [] as $review) {
            $reviews[] = [
                'googleReviewId'    => $review['name'] ?? uniqid('review_'),
                'googleAuthor'      => $review['authorAttribution']['displayName'] ?? 'Anonyme',
                'googleAuthorPhoto' => $review['authorAttribution']['photoUri'] ?? null,
                'rating'            => $review['rating'] ?? 0,
                'text'              => $review['text']['text'] ?? null,
                'publishedAt'       => isset($review['publishTime'])
                    ? new \DateTimeImmutable($review['publishTime'])
                    : new \DateTimeImmutable(),
            ];
        }

        return [
            'name'    => $data['displayName']['text'] ?? '',
            'address' => $data['formattedAddress'] ?? '',
            'rating'  => $data['rating'] ?? null,
            'reviews' => $reviews,
        ];
    }
}
