<?php

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class EstablishmentControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function postJson(string $url, array $data, ?string $token = null): Response
    {
        $headers = [
            'CONTENT_TYPE' => 'application/json',
        ];

        if (null !== $token) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request(
            'POST',
            $url,
            [],
            [],
            $headers,
            json_encode($data, JSON_THROW_ON_ERROR)
        );

        return $this->client->getResponse();
    }

    private function getJson(string $url, ?string $token = null): Response
    {
        $headers = [];

        if (null !== $token) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }

        $this->client->request('GET', $url, [], [], $headers);

        return $this->client->getResponse();
    }

    private function registerAndLogin(string $email): string
    {
        $this->postJson('/api/auth/register', [
            'email' => $email,
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'password123',
        ]);

        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('token', $data);

        return $data['token'];
    }

    public function testListRequiresAuth(): void
    {
        $response = $this->getJson('/api/establishments');

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testListWithValidToken(): void
    {
        $token = $this->registerAndLogin('listtest_'.uniqid().'@test.fr');

        $response = $this->getJson('/api/establishments', $token);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($data);
    }

    public function testCreateEstablishment(): void
    {
        $token = $this->registerAndLogin('createtest_'.uniqid().'@test.fr');

        $response = $this->postJson('/api/establishments', [
            'name' => 'Ma Boulangerie',
            'placeId' => 'ChIJtest'.uniqid(),
            'address' => '12 rue de la Paix, 75001 Paris',
            'alertsEnabled' => true,
        ], $token);

        $this->assertSame(Response::HTTP_CREATED, $response->getStatusCode());

        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('Ma Boulangerie', $data['name']);
        $this->assertArrayHasKey('id', $data);
    }

    public function testCreateEstablishmentMissingName(): void
    {
        $token = $this->registerAndLogin('missingname_'.uniqid().'@test.fr');

        $response = $this->postJson('/api/establishments', [
            'placeId' => 'ChIJtest123',
            'address' => '12 rue Test',
        ], $token);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function testCannotAccessOtherUserEstablishment(): void
    {
        $tokenA = $this->registerAndLogin('userA_'.uniqid().'@test.fr');

        $createResponse = $this->postJson('/api/establishments', [
            'name' => 'Établissement de A',
            'placeId' => 'ChIJuserA'.uniqid(),
            'address' => '1 rue A',
        ], $tokenA);

        $createdData = json_decode(
            $createResponse->getContent(),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $this->assertArrayHasKey('id', $createdData);

        $establishmentId = $createdData['id'];

        $tokenB = $this->registerAndLogin('userB_'.uniqid().'@test.fr');

        $response = $this->getJson(
            '/api/establishments/'.$establishmentId,
            $tokenB
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }
}
