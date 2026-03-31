<?php

namespace App\Tests\Unit\Service;

use App\Entity\Establishment;
use App\Entity\Review;
use App\Entity\User;
use App\Service\AlertEmailService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Twig\Environment;

class AlertEmailServiceTest extends TestCase
{
    private function buildEstablishment(bool $establishmentAlerts, bool $userAlerts): Establishment
    {
        $user = new User();
        $user->setEmail('test@test.fr');
        $user->setAlertsEnabled($userAlerts);
        $user->setPassword('hashed');

        $establishment = new Establishment();
        $establishment->setOwner($user);
        $establishment->setName('Test Etablissement');
        $establishment->setPlaceId('ChIJtest');
        $establishment->setAddress('1 rue Test');
        $establishment->setAlertsEnabled($establishmentAlerts);

        return $establishment;
    }

    public function testAlertSentForNegativeReview(): void
    {
        $establishment = $this->buildEstablishment(true, true);

        $review = new Review();
        $review->setRating(1);
        $review->setGoogleAuthor('Client mécontent');
        $review->setGoogleReviewId('test-id');
        $review->setPublishedAt(new \DateTimeImmutable());

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())->method('send');

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn('<html>email</html>');

        $service = new AlertEmailService($mailer, $twig);
        $service->sendNegativeReviewAlert($establishment, $review);
    }

    public function testAlertNotSentWhenEstablishmentAlertsDisabled(): void
    {
        $establishment = $this->buildEstablishment(false, true);

        $review = new Review();
        $review->setRating(1);
        $review->setGoogleAuthor('Client');
        $review->setGoogleReviewId('test-id');
        $review->setPublishedAt(new \DateTimeImmutable());

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $twig = $this->createMock(Environment::class);

        $service = new AlertEmailService($mailer, $twig);
        $service->sendNegativeReviewAlert($establishment, $review);
    }

    public function testAlertNotSentWhenUserAlertsDisabled(): void
    {
        $establishment = $this->buildEstablishment(true, false);

        $review = new Review();
        $review->setRating(2);
        $review->setGoogleAuthor('Client');
        $review->setGoogleReviewId('test-id');
        $review->setPublishedAt(new \DateTimeImmutable());

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $twig = $this->createMock(Environment::class);

        $service = new AlertEmailService($mailer, $twig);
        $service->sendNegativeReviewAlert($establishment, $review);
    }
}
