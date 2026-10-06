<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Entity\Establishment;
use App\Repository\EstablishmentRepository;
use App\Repository\ReviewRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Scheduler\Attribute\AsCronTask;
use Twig\Environment;

/**
 * Le 1er de chaque mois à 9 h : bilan du mois écoulé envoyé à chaque commerçant
 * dont l'e-mail est renseigné dans les paramètres de l'établissement.
 */
#[AsCronTask('0 9 1 * *')]
class MonthlyClientReportTask
{
    public function __construct(
        private EstablishmentRepository $establishmentRepository,
        private ReviewRepository $reviewRepository,
        private MailerInterface $mailer,
        private Environment $twig,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(): void
    {
        $from = new \DateTimeImmutable('first day of last month midnight');
        $to = new \DateTimeImmutable('first day of this month midnight');

        foreach ($this->establishmentRepository->findAll() as $establishment) {
            if (null === $establishment->getClientEmail()) {
                continue;
            }

            try {
                $this->send($establishment, $from, $to);
            } catch (\Throwable $e) {
                $this->logger->error('Bilan mensuel non envoyé', [
                    'establishment' => (string) $establishment->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function send(Establishment $establishment, \DateTimeImmutable $from, \DateTimeImmutable $to): void
    {
        $stats = $this->reviewRepository->getPeriodStats($establishment, $from, $to);

        $html = $this->twig->render('emails/monthly_client_report.html.twig', [
            'establishment' => $establishment,
            'stats' => $stats,
            'month' => $from,
        ]);

        $email = (new Email())
            ->to((string) $establishment->getClientEmail())
            ->subject(sprintf('Vos avis Google en %s — %s', $this->monthLabel($from), $establishment->getName()))
            ->html($html);

        // Le commerçant répond directement au gestionnaire (le propriétaire du compte).
        $ownerEmail = $establishment->getOwner()?->getEmail();
        if (null !== $ownerEmail) {
            $email->replyTo($ownerEmail);
        }

        $this->mailer->send($email);
    }

    private function monthLabel(\DateTimeImmutable $date): string
    {
        $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        return $months[(int) $date->format('n') - 1].' '.$date->format('Y');
    }
}
