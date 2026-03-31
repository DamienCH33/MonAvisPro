<?php

namespace App\Tests\Unit\Service;

use App\Service\LlmService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class LlmServiceTest extends TestCase
{
    public function testAnalyzeReviewsReturnsValidJson(): void
    {
        $validJson = json_encode([
            'positive_themes' => [
                ['theme' => 'accueil', 'percentage' => 80, 'example' => 'Super accueil'],
            ],
            'negative_themes' => [
                ['theme' => 'attente', 'percentage' => 30, 'example' => 'Longue attente'],
            ],
            'action_suggestion' => 'Réduire le temps d\'attente.',
        ]);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'choices' => [
                ['message' => ['content' => $validJson]],
            ],
        ]);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        $service = new LlmService($httpClient, 'fake-api-key');
        $result = $service->analyzeReviews([
            ['rating' => 5, 'text' => 'Super accueil'],
            ['rating' => 2, 'text' => 'Longue attente'],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('positive_themes', $result);
        $this->assertArrayHasKey('negative_themes', $result);
        $this->assertArrayHasKey('action_suggestion', $result);
    }

    public function testAnalyzeReviewsReturnsNullOnApiError(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willThrowException(new \Exception('API Error'));

        $service = new LlmService($httpClient, 'fake-api-key');
        $result = $service->analyzeReviews([['rating' => 5, 'text' => 'Test']]);

        $this->assertNull($result);
    }
}
