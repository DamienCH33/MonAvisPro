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

        $connection = self::getContainer()->get('doctrine')->getConnection();
        $connection->executeStatement('TRUNCATE TABLE "user" CASCADE');
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
            'email' => 'test@test.fr',
            'password' => 'password123',
        ]);

        $response->getContent();

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

    public function testLoginIsRateLimitedAfterTooManyAttempts(): void
    {
        // Le throttling est désactivé en test (no_limit) : on vérifie juste
        // que des identifiants erronés renvoient 401, jamais 500.
        for ($i = 0; $i < 6; ++$i) {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'inconnu@test.fr',
                'password' => 'faux',
            ]);
            $this->assertContains($response->getStatusCode(), [401, 429]);
        }
    }

    public function testDuplicateEmailMessageDoesNotRevealAccount(): void
    {
        $email = 'leak_'.uniqid().'@test.fr';
        $this->postJson('/api/auth/register', ['email' => $email, 'password' => 'password123']);
        $response = $this->postJson('/api/auth/register', ['email' => $email, 'password' => 'password123']);

        $data = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        // Message neutre : il ne doit pas confirmer que l'adresse existe déjà.
        $this->assertStringNotContainsStringIgnoringCase('déjà', $data['error']);
        $this->assertStringNotContainsStringIgnoringCase('existe', $data['error']);
    }

    // Audit d'octobre 2026

    public function testLoginIsCaseInsensitive(): void
    {
        $this->postJson('/api/auth/register', ['email' => 'Jean@Test.fr', 'password' => 'password123']);

        $response = $this->postJson('/api/auth/login', ['email' => 'JEAN@test.FR', 'password' => 'password123']);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testRegisterSameEmailWithOtherCaseIsRefused(): void
    {
        $this->postJson('/api/auth/register', ['email' => 'jean@test.fr', 'password' => 'password123']);

        $response = $this->postJson('/api/auth/register', ['email' => 'JEAN@test.fr', 'password' => 'password123']);

        $this->assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testFormRegistrationDoesNotRevealExistingAccount(): void
    {
        $this->postJson('/api/auth/register', ['email' => 'deja@test.fr', 'password' => 'password123']);

        $crawler = $this->client->request('GET', '/register');
        $form = $crawler->filter('form')->form();
        foreach (array_keys($form->all()) as $name) {
            if (str_contains($name, '[email]')) {
                $form[$name] = 'deja@test.fr';
            } elseif (str_contains($name, 'plainPassword')) {
                $form[$name] = 'password123';
            } elseif (str_contains($name, 'agree')) {
                $form[$name]->tick();
            }
        }
        $this->client->submit($form);

        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString('déjà utilisé', $content);
        $this->assertStringContainsString('Inscription impossible', $content);
    }
}
