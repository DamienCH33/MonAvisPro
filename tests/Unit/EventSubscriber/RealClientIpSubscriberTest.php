<?php

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\RealClientIpSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class RealClientIpSubscriberTest extends TestCase
{
    protected function setUp(): void
    {
        // Comme en prod sur Railway : le proxy qui nous parle (REMOTE_ADDR) est de confiance.
        Request::setTrustedProxies(['10.0.0.5'], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO);
    }

    protected function tearDown(): void
    {
        Request::setTrustedProxies([], -1);
    }

    private function handle(Request $request, string $header = 'X-Real-IP'): Request
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        (new RealClientIpSubscriber($header))->onRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));

        return $request;
    }

    public function testRailwayRelayIpIsReplacedByTheRealVisitorIp(): void
    {
        $request = Request::create('/', server: [
            'REMOTE_ADDR' => '10.0.0.5',
            // Fausse IP du visiteur à gauche, IP d'un relais Railway à droite.
            'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 66.33.22.11',
            'HTTP_X_REAL_IP' => '82.64.10.20',
        ]);

        $this->assertSame('66.33.22.11', $request->getClientIp(), 'sans correctif : IP du relais');
        $this->assertSame('82.64.10.20', $this->handle($request)->getClientIp());
    }

    public function testInvalidHeaderIsIgnored(): void
    {
        $request = $this->handle(Request::create('/', server: [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '66.33.22.11',
            'HTTP_X_REAL_IP' => 'pas-une-ip',
        ]));

        $this->assertSame('66.33.22.11', $request->getClientIp());
    }

    public function testDisabledWhenHeaderIsEmpty(): void
    {
        $request = $this->handle(Request::create('/', server: [
            'REMOTE_ADDR' => '10.0.0.5',
            'HTTP_X_FORWARDED_FOR' => '66.33.22.11',
            'HTTP_X_REAL_IP' => '82.64.10.20',
        ]), '');

        $this->assertSame('66.33.22.11', $request->getClientIp());
    }

    public function testIgnoredWhenNotBehindTheTrustedProxy(): void
    {
        Request::setTrustedProxies([], -1);

        $request = $this->handle(Request::create('/', server: [
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_X_REAL_IP' => '82.64.10.20',
        ]));

        $this->assertSame('203.0.113.9', $request->getClientIp());
    }
}
