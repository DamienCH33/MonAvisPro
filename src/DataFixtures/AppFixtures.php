<?php

namespace App\DataFixtures;

use App\Factory\EstablishmentFactory;
use App\Factory\ReviewAnalysisFactory;
use App\Factory\ReviewFactory;
use App\Factory\UserFactory;
use App\Service\DemoSeeder;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function __construct(private readonly DemoSeeder $demoSeeder)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = \Faker\Factory::create('fr_FR');

        // Utilisateur de test
        $testUser = UserFactory::createOne([
            'email' => 'demo@monavispro.fr',
            'password' => 'demo1234',
            'alertsEnabled' => false,
        ]);

        $this->demoSeeder->seed($testUser->_real());

        // Autres utilisateurs
        $otherUsers = UserFactory::createMany(4);

        foreach ($otherUsers as $user) {
            $establishments = EstablishmentFactory::createMany(
                $faker->numberBetween(1, 3),
                fn () => ['owner' => $user]
            );

            foreach ($establishments as $estab) {
                ReviewFactory::createMany(
                    $faker->numberBetween(5, 20),
                    fn () => ['establishment' => $estab]
                );

                if ($faker->boolean(60)) {
                    ReviewAnalysisFactory::createOne(['establishment' => $estab]);
                }
            }
        }

        $manager->flush();
    }
}
