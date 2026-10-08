<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Repository\UserRepository;
use App\Security\DemoAccountGuard;
use App\Service\DemoSeeder;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

/** Remet le compte de démonstration à neuf chaque nuit (les visiteurs y publient des réponses). */
#[AsCronTask('15 4 * * *')]
final class DemoResetTask
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly DemoSeeder $seeder,
    ) {
    }

    public function __invoke(): void
    {
        $demo = $this->users->findOneBy(['email' => DemoAccountGuard::DEMO_EMAIL]);
        if (null !== $demo) {
            $this->seeder->reset($demo);
        }
    }
}
