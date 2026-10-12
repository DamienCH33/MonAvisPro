<?php

namespace App\Tests\Unit\Service;

use App\Service\GoogleApiErrorDescriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GoogleApiErrorDescriberTest extends TestCase
{
    private function googleError(int $status, string $body): \Throwable
    {
        $client = new MockHttpClient(new MockResponse($body, ['http_code' => $status]));

        try {
            $client->request('GET', 'https://mybusiness.googleapis.com/v4/x')->toArray();
        } catch (\Throwable $e) {
            return $e;
        }

        self::fail('Une exception était attendue.');
    }

    public function testApiNotEnabledGivesReadableMessageAndLogsGoogleBody(): void
    {
        $body = '{"error":{"code":403,"status":"PERMISSION_DENIED","details":[{"reason":"SERVICE_DISABLED"}]}}';

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(self::anything(), self::callback(
                static fn (array $ctx): bool => 403 === $ctx['status'] && str_contains((string) $ctx['google_response'], 'SERVICE_DISABLED')
            ));

        $message = (new GoogleApiErrorDescriber($logger))->describe($this->googleError(403, $body), 'test');

        self::assertStringContainsString('n\'est pas encore activé', $message);
        self::assertStringNotContainsString('googleapis', $message);
    }

    public function testQuotaExceeded(): void
    {
        $message = (new GoogleApiErrorDescriber($this->createStub(LoggerInterface::class)))
            ->describe($this->googleError(429, '{"error":{"code":429}}'), 'test');

        self::assertStringContainsString('limite temporairement', $message);
    }

    public function testExpiredToken(): void
    {
        $message = (new GoogleApiErrorDescriber($this->createStub(LoggerInterface::class)))
            ->describe($this->googleError(400, '{"error":"invalid_grant"}'), 'test');

        self::assertStringContainsString('expiré', $message);
    }

    public function testNonHttpException(): void
    {
        $message = (new GoogleApiErrorDescriber($this->createStub(LoggerInterface::class)))
            ->describe(new \RuntimeException('boom'), 'test');

        self::assertStringContainsString('Réessayez', $message);
    }
}
