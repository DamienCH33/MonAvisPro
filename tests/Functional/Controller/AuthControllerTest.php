<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AuthControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function postJson(string $url, array $data): Response
    {
        $this->client->request(
            'POST',
            $url,
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
            ],
            json_encode($data, JSON_THROW_ON_ERROR)
        );

        return $this->client->getResponse();
    }

    // Register

    public function testRegisterSuccess(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'newuser_'.uniqid().'@test.fr',
            'password' => 'password123',
        ]);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $data = json_decode(
            $response->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('email', $data['user']);
    }

    public function testRegisterDuplicateEmail(): void
    {
        $email = 'duplicate_'.uniqid().'@test.fr';

        $this->postJson('/api/auth/register', [
            'email' => $email,
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/auth/register', [
            'email' => $email,
            'password' => 'password123',
        ]);

        $this->assertSame(422, $response->getStatusCode());

        $data = json_decode(
            $response->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertArrayHasKey('error', $data);
    }

    public function testRegisterInvalidEmail(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'pas-un-email',
            'password' => 'password123',
        ]);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function testRegisterPasswordTooShort(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'short_'.uniqid().'@test.fr',
            'password' => '123',
        ]);

        $this->assertSame(422, $response->getStatusCode());
    }

    // Login

    public function testLoginSuccess(): void
    {
        $email = 'logintest_'.uniqid().'@test.fr';

        $this->postJson('/api/auth/register', [
            'email' => $email,
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ]);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode(
            $response->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertArrayHasKey('token', $data);
        $this->assertNotEmpty($data['token']);
    }

    public function testLoginWrongPassword(): void
    {
        $email = 'wrongpass_'.uniqid().'@test.fr';

        $this->postJson('/api/auth/register', [
            'email' => $email,
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'mauvaismdp',
        ]);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testLoginUnknownEmail(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'inconnu_'.uniqid().'@test.fr',
            'password' => 'password123',
        ]);

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }
}
