<?php

namespace App\Scheduler;

use App\Repository\EstablishmentRepository;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Scheduler\Attribute\AsCronTask;
use Twig\Environment;

#[AsCronTask('0 8 * * 1')] // Lundi à 8h
class WeeklyReportTask
{
    public function __construct(
        private UserRepository $userRepository,
        private EstablishmentRepository $establishmentRepository,
        private ReviewRepository $reviewRepository,
        private MailerInterface $mailer,
        private Environment $twig,
    ) {
    }

    public function __invoke(): void
    {
        $users = $this->userRepository->findBy(['alertsEnabled' => true]);

        foreach ($users as $user) {
            $establishments = $this->establishmentRepository->findBy(['owner' => $user]);

            if (empty($establishments)) {
                continue;
            }

            $reportData = [];

            foreach ($establishments as $establishment) {
                $since = new \DateTimeImmutable('-7 days');

                $newReviews = $this->reviewRepository->findNewReviewsSince(
                    $establishment,
                    $since
                );

                $allReviews = $this->reviewRepository->findBy(['establishment' => $establishment]);
                $average = empty($allReviews) ? null : round(
                    array_sum(array_map(fn ($r) => $r->getRating(), $allReviews)) / count($allReviews),
                    1
                );

                $reportData[] = [
                    'establishment' => $establishment,
                    'newReviews' => $newReviews,
                    'newCount' => count($newReviews),
                    'average' => $average,
                    'unread' => count(array_filter($newReviews, fn ($r) => !$r->isRead())),
                ];
            }

            $html = $this->twig->render('emails/weekly_report.html.twig', [
                'user' => $user,
                'reportData' => $reportData,
                'weekStart' => new \DateTimeImmutable('-7 days'),
            ]);

            $email = (new Email())
                ->from('noreply@monavispro.fr')
                ->to($user->getEmail())
                ->subject('📊 Votre rapport hebdomadaire MonAvisPro')
                ->html($html);

            $this->mailer->send($email);
        }
    }
}
