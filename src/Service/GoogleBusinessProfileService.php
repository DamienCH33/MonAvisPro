<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleBusinessProfileService
{
    private const OAUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const BUSINESS_API_URL = 'https://mybusinessbusinessinformation.googleapis.com/v1';
    private const ACCOUNT_API_URL = 'https://mybusinessaccountmanagement.googleapis.com/v1';
    // Les avis et leurs réponses restent sur l'API v4 (My Business).
    private const REVIEWS_API_URL = 'https://mybusiness.googleapis.com/v4';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
    ) {
    }

    public function getAuthorizationUrl(string $state): string
    {
        return self::OAUTH_URL.'?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/business.manage',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    /** @return array<string, mixed> */
    public function exchangeCodeForToken(string $code): array
    {
        return $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => [
                'code' => $code,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->redirectUri,
                'grant_type' => 'authorization_code',
            ],
        ])->toArray();
    }

    /** @return array<int, array<string, mixed>> */
    public function getAccounts(string $accessToken): array
    {
        return $this->httpClient->request('GET', self::ACCOUNT_API_URL.'/accounts', [
            'headers' => ['Authorization' => 'Bearer '.$accessToken],
        ])->toArray()['accounts'] ?? [];
    }

    /** @return array<int, array<string, mixed>> */
    public function getLocations(string $accountId, string $accessToken): array
    {
        return $this->httpClient->request(
            'GET',
            self::BUSINESS_API_URL.'/'.$accountId.'/locations',
            [
                'headers' => ['Authorization' => 'Bearer '.$accessToken],
                'query' => ['readMask' => 'name,title,storefrontAddress,phoneNumbers,websiteUri,metadata'],
            ]
        )->toArray()['locations'] ?? [];
    }

    /**
     * Une page d'avis d'un établissement (50 maximum), avec les réponses déjà publiées.
     *
     * @param string $accountId  ex. « accounts/123 »
     * @param string $locationId ex. « locations/456 »
     *
     * @return array{reviews?: list<array<string, mixed>>, nextPageToken?: string, totalReviewCount?: int, averageRating?: float}
     */
    public function listReviews(string $accountId, string $locationId, string $accessToken, ?string $pageToken = null): array
    {
        $query = ['pageSize' => 50, 'orderBy' => 'updateTime desc'];
        if (null !== $pageToken) {
            $query['pageToken'] = $pageToken;
        }

        /** @var array{reviews?: list<array<string, mixed>>, nextPageToken?: string, totalReviewCount?: int, averageRating?: float} $data */
        $data = $this->httpClient->request(
            'GET',
            self::REVIEWS_API_URL.'/'.$accountId.'/'.$locationId.'/reviews',
            [
                'headers' => ['Authorization' => 'Bearer '.$accessToken],
                'query' => $query,
            ]
        )->toArray();

        return $data;
    }

    /** @return array<string, mixed> */
    public function publishReply(string $reviewName, string $replyText, string $accessToken): array
    {
        return $this->httpClient->request(
            'PUT',
            self::REVIEWS_API_URL.'/'.$reviewName.'/reply',
            [
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => ['comment' => $replyText],
            ]
        )->toArray();
    }

    /** @return array<string, mixed> */
    public function refreshAccessToken(string $refreshToken): array
    {
        return $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ],
        ])->toArray();
    }

    public function deleteReply(string $reviewName, string $accessToken): void
    {
        $this->httpClient->request(
            'DELETE',
            self::REVIEWS_API_URL.'/'.$reviewName.'/reply',
            [
                'headers' => ['Authorization' => 'Bearer '.$accessToken],
            ]
        );
    }
}
