<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleBusinessProfileService
{
    private const OAUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const BUSINESS_API_URL = 'https://mybusinessbusinessinformation.googleapis.com/v1';
    private const ACCOUNT_API_URL = 'https://mybusinessaccountmanagement.googleapis.com/v1';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri
    ) {}

    public function getAuthorizationUrl(string $state): string
    {
        $params = http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/business.manage',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return self::OAUTH_URL . '?' . $params;
    }

    public function exchangeCodeForToken(string $code): array
    {
        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => [
                'code' => $code,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->redirectUri,
                'grant_type' => 'authorization_code',
            ],
        ]);

        return $response->toArray();
    }

    public function getAccounts(string $accessToken): array
    {
        $response = $this->httpClient->request('GET', self::ACCOUNT_API_URL . '/accounts', [
            'headers' => ['Authorization' => 'Bearer ' . $accessToken],
        ]);

        return $response->toArray()['accounts'] ?? [];
    }

    public function getLocations(string $accountId, string $accessToken): array
    {
        $response = $this->httpClient->request(
            'GET',
            self::BUSINESS_API_URL . '/' . $accountId . '/locations',
            [
                'headers' => ['Authorization' => 'Bearer ' . $accessToken],
                'query' => ['readMask' => 'name,title,storefrontAddress,phoneNumbers,websiteUri'],
            ]
        );

        return $response->toArray()['locations'] ?? [];
    }

    public function publishReply(
        string $reviewName,
        string $replyText,
        string $accessToken
    ): array {
        $response = $this->httpClient->request(
            'PUT',
            self::BUSINESS_API_URL . '/' . $reviewName . '/reply',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ],
                'json' => ['comment' => $replyText],
            ]
        );

        return $response->toArray();
    }

    public function deleteReply(string $reviewName, string $accessToken): void
    {
        $this->httpClient->request(
            'DELETE',
            self::BUSINESS_API_URL . '/' . $reviewName . '/reply',
            [
                'headers' => ['Authorization' => 'Bearer ' . $accessToken],
            ]
        );
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'body' => [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ],
        ]);

        return $response->toArray();
    }
}
