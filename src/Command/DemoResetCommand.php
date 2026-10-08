<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\DemoAccountGuard;
use App\Service\DemoSeeder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:demo:reset', description: 'Recrée les données du compte de démonstration (créé s\'il n\'existe pas).')]
final class DemoResetCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly DemoSeeder $seeder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $demo = $this->users->findOneBy(['email' => DemoAccountGuard::DEMO_EMAIL]);
        if (null === $demo) {
            $demo = (new User())->setEmail(DemoAccountGuard::DEMO_EMAIL);
            $demo->setPassword($this->hasher->hashPassword($demo, 'demo1234'));
            $demo->setAlertsEnabled(false);
            $this->em->persist($demo);
            $this->em->flush();
        }

        $this->seeder->reset($demo);
        $io->success('Compte de démonstration réinitialisé.');

        return Command::SUCCESS;
    }
}
