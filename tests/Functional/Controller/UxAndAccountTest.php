<?php

namespace App\Tests\Functional\Controller;

use App\Entity\Establishment;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Branche feature/ux-et-compte : Ignorer, À traiter paginé, écran de bienvenue, Mon compte.
 */
class UxAndAccountTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = self::getContainer()->get('doctrine')->getManager();
    }

    private function createUser(?string $email = null): User
    {
        $hasher = self::getContainer()->get('security.user_password_hasher');
        $user = (new User())->setEmail($email ?? 'ux_'.uniqid().'@test.fr')->setCreatedAt(new \DateTimeImmutable());
        $user->setPassword($hasher->hashPassword($user, 'password123'));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /** @return array<string, string> en-tête d'authentification de l'API (pare-feu JWT) */
    private function jwt(User $user): array
    {
        $token = self::getContainer()->get('lexik_jwt_authentication.jwt_manager')->create($user);

        return ['HTTP_AUTHORIZATION' => 'Bearer '.$token];
    }

    /** @param list<int> $ratings */
    private function createEstablishment(User $owner, array $ratings): Establishment
    {
        $establishment = (new Establishment())
            ->setOwner($owner)
            ->setName('Boulangerie test')
            ->setPlaceId('ChIJux'.uniqid())
            ->setAddress('1 rue Test');
        $this->em->persist($establishment);

        foreach ($ratings as $i => $rating) {
            $review = (new Review())
                ->setEstablishment($establishment)
                ->setGoogleAuthor('Client '.$i)
                ->setRating($rating)
                ->setPublishedAt(new \DateTimeImmutable('-'.$i.' day'))
                ->setGoogleReviewId('ux-'.uniqid().'-'.$i);
            $this->em->persist($review);
        }
        $this->em->flush();

        return $establishment;
    }

    public function testWelcomeScreenWithoutEstablishment(): void
    {
        $this->client->loginUser($this->createUser());
        $this->client->request('GET', '/dashboard');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Reliez votre fiche Google');
        $this->assertSelectorExists('a[href="/google/connect"]');
    }

    public function testIgnoreRemovesReviewFromInboxAndUndoPutsItBack(): void
    {
        $user = $this->createUser();
        $establishment = $this->createEstablishment($user, [1, 5]);
        $review = $this->em->getRepository(Review::class)->findOneBy(['establishment' => $establishment, 'rating' => 1]);
        $this->client->loginUser($user);

        $this->client->request('PATCH', '/api/reviews/'.$review->getId().'/ignore', [], [], $this->jwt($user));
        $this->assertResponseIsSuccessful();

        $crawler = $this->client->request('GET', '/dashboard/a-traiter');
        $this->assertSame(1, $crawler->filter('[data-review-id]')->count());
        $this->assertSelectorTextContains('h1', '1 avis attend une réponse');

        $this->client->request('PATCH', '/api/reviews/'.$review->getId().'/unignore', [], [], $this->jwt($user));
        $crawler = $this->client->request('GET', '/dashboard/a-traiter');
        $this->assertSame(2, $crawler->filter('[data-review-id]')->count());
    }

    public function testCannotIgnoreSomeoneElsesReview(): void
    {
        $owner = $this->createUser();
        $establishment = $this->createEstablishment($owner, [2]);
        $review = $this->em->getRepository(Review::class)->findOneBy(['establishment' => $establishment]);

        $this->client->request('PATCH', '/api/reviews/'.$review->getId().'/ignore', [], [], $this->jwt($this->createUser()));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testInboxShowsHundredThenLoadMore(): void
    {
        $user = $this->createUser();
        $this->createEstablishment($user, array_fill(0, 120, 4));
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/dashboard/a-traiter');
        $this->assertSame(100, $crawler->filter('[data-review-id]')->count());
        $this->assertSelectorTextContains('.rr-load-more', '100 avis affichés sur 120');
        $this->assertSelectorTextContains('.rr-load-more a', 'Afficher 20 avis de plus');

        $crawler = $this->client->request('GET', '/dashboard/a-traiter?afficher=150');
        $this->assertSame(120, $crawler->filter('[data-review-id]')->count());
        $this->assertSelectorNotExists('.rr-load-more');
    }

    public function testExportContainsReviewsButNoSecrets(): void
    {
        $user = $this->createUser();
        $establishment = $this->createEstablishment($user, [3]);
        $establishment->setGoogleRefreshToken('secret-refresh-token');
        $this->em->flush();
        $this->client->loginUser($user);

        $this->client->request('GET', '/dashboard/compte/export');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringNotContainsString('secret-refresh-token', $content);
        $this->assertStringNotContainsString($user->getPassword(), $content);
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $data['etablissements'][0]['avis']);
    }

    public function testDeleteAccountNeedsThePassword(): void
    {
        $user = $this->createUser();
        $this->createEstablishment($user, [2, 4]);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/dashboard/compte');
        $form = $crawler->filter('#delete-dialog form')->form(['password' => 'mauvais']);
        $this->client->submit($form);

        $this->assertResponseRedirects('/dashboard/compte');
        $this->em->clear();
        $this->assertNotNull($this->em->getRepository(User::class)->find($user->getId()));
    }

    public function testDeleteAccountErasesEverything(): void
    {
        $user = $this->createUser();
        $userId = $user->getId();
        $establishment = $this->createEstablishment($user, [2, 4]);
        $establishmentId = $establishment->getId();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/dashboard/compte');
        $form = $crawler->filter('#delete-dialog form')->form(['password' => 'password123']);
        $this->client->submit($form);

        $this->assertResponseRedirects('/');
        $this->em->clear();
        $this->assertNull($this->em->getRepository(User::class)->find($userId));
        $this->assertNull($this->em->getRepository(Establishment::class)->find($establishmentId));
        $this->assertSame(0, $this->em->getRepository(Review::class)->count(['establishment' => $establishmentId]));

        $this->client->request('GET', '/dashboard');
        $this->assertResponseRedirects();
    }

    public function testLegalPageShowsEstablishmentsWhenLoggedIn(): void
    {
        $user = $this->createUser();
        $this->createEstablishment($user, []);
        $this->client->loginUser($user);

        $this->client->request('GET', '/privacy');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.rr-sidebar', 'Boulangerie test');
    }
}
