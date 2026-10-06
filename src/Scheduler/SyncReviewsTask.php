<?php

namespace App\Scheduler;

use App\Repository\EstablishmentRepository;
use App\Service\ReviewSyncService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsPeriodicTask(frequency: '6 hours', jitter: 60)]
class SyncReviewsTask
{
    public function __construct(
        private EstablishmentRepository $establishmentRepository,
        private ReviewSyncService $reviewSyncService,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(): void
    {
        $establishments = $this->establishmentRepository->findAll();

        foreach ($establishments as $establishment) {
            // Une fiche en erreur (jeton révoqué, quota Google…) ne doit pas bloquer les suivantes.
            try {
                $this->reviewSyncService->sync($establishment);
            } catch (\Throwable $e) {
                $this->logger->error('Synchronisation des avis impossible', [
                    'establishment' => (string) $establishment->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
