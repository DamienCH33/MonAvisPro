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

    public function testGenerateReplyAppliesEstablishmentSettings(): void
    {
        $sentPayload = null;

        $response = $this->createStub(ResponseInterface::class);
        $response->method('toArray')->willReturn([
            'choices' => [['message' => ['content' => '« Merci beaucoup Marie, à très vite ! »']]],
        ]);

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')->willReturnCallback(
            function (string $method, string $url, array $options) use (&$sentPayload, $response) {
                $sentPayload = $options['json'];

                return $response;
            }
        );

        $service = new LlmService($httpClient, 'fake-api-key');
        $reply = $service->generateReply(
            'Fournil Béglais',
            5,
            'Pain délicieux',
            'cordial',
            'vous',
            "L'équipe du Fournil Béglais",
            'Fermé le lundi.',
            'Marie',
        );

        $this->assertSame("Merci beaucoup Marie, à très vite !\nL'équipe du Fournil Béglais", $reply);

        $this->assertIsArray($sentPayload);
        $system = $sentPayload['messages'][0]['content'];
        $this->assertStringContainsString('Vouvoie', $system);
        $this->assertStringContainsString("L'équipe du Fournil Béglais", $system);
        $this->assertStringContainsString('Fermé le lundi.', $system);
        $this->assertStringContainsString('Marie', $sentPayload['messages'][1]['content']);
    }

    public function testGenerateReplyWithTutoiementAndNoSignature(): void
    {
        $service = new LlmService($this->createStub(HttpClientInterface::class), 'fake-api-key');
        $prompt = $service->buildReplySystemPrompt('Le Studio', 'empathique', 'tu', null, null);

        $this->assertStringContainsString('Tutoie', $prompt);
        $this->assertStringContainsString('Ne signe pas', $prompt);
        $this->assertStringContainsString('empathique', $prompt);
    }
}
