<?php

namespace App\Command;

use App\Repository\EstablishmentRepository;
use App\Repository\ReviewRepository;
use App\Service\AlertEmailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:test-email')]
class TestEmailCommand extends Command
{
    public function __construct(
        private AlertEmailService $alertEmailService,
        private EstablishmentRepository $establishmentRepository,
        private ReviewRepository $reviewRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Récupère le premier établissement en base
        $establishment = $this->establishmentRepository->findOneBy([]);
        $review = $this->reviewRepository->findOneBy(['rating' => 1]);

        if (!$establishment || !$review) {
            $output->writeln('<error>Pas de données en base.</error>');
            return Command::FAILURE;
        }

        $this->alertEmailService->sendNegativeReviewAlert($establishment, $review);

        $output->writeln('<info>Email envoyé ! Vérifie Mailtrap.</info>');
        return Command::SUCCESS;
    }
}
