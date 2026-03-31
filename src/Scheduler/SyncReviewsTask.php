<?php

namespace App\Scheduler;

use App\Repository\EstablishmentRepository;
use App\Service\ReviewSyncService;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

#[AsPeriodicTask(frequency: '6 hours', jitter: 60)]
class SyncReviewsTask
{
    public function __construct(
        private EstablishmentRepository $establishmentRepository,
        private ReviewSyncService $reviewSyncService,
    ) {
    }

    public function __invoke(): void
    {
        $establishments = $this->establishmentRepository->findAll();

        foreach ($establishments as $establishment) {
            if ($establishment->isAlertsEnabled()) {
                $this->reviewSyncService->sync($establishment);
            }
        }
    }
}
