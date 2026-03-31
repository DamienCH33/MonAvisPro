<?php

namespace App\DataFixtures;

use App\Factory\EstablishmentFactory;
use App\Factory\ReviewAnalysisFactory;
use App\Factory\ReviewFactory;
use App\Factory\UserFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = \Faker\Factory::create('fr_FR');

        // Utilisateur de test
        $testUser = UserFactory::createOne([
            'email' => 'demo@monavispro.fr',
            'password' => 'demo1234',
            'alertsEnabled' => true,
        ]);

        // Ses établissements
        $establishment1 = EstablishmentFactory::createOne([
            'owner' => $testUser,
            'name' => 'Boulangerie Du Coin',
            'address' => '14 rue de la République, 31000 Toulouse',
            'alertsEnabled' => true,
        ]);

        $establishment2 = EstablishmentFactory::createOne([
            'owner' => $testUser,
            'name' => 'Salon Coiffure Zen',
            'address' => '3 allée Jean Jaurès, 31000 Toulouse',
            'alertsEnabled' => false,
        ]);

        // Avis pour l'établissement 1
        ReviewFactory::createMany(12, fn () => [
            'establishment' => $establishment1,
            'rating' => $faker->numberBetween(4, 5),
            'isRead' => true,
        ]);

        ReviewFactory::createMany(3, fn () => [
            'establishment' => $establishment1,
            'rating' => $faker->numberBetween(1, 2),
            'isRead' => false,
        ]);

        ReviewFactory::createMany(2, fn () => [
            'establishment' => $establishment1,
            'rating' => 3,
        ]);

        // Avis pour l'établissement 2
        ReviewFactory::createMany(8, fn () => [
            'establishment' => $establishment2,
            'rating' => $faker->numberBetween(3, 5),
        ]);

        ReviewFactory::createMany(2, fn () => [
            'establishment' => $establishment2,
            'rating' => $faker->numberBetween(1, 2),
            'isRead' => false,
        ]);

        // Analyse LLM pour l'établissement 1
        ReviewAnalysisFactory::createOne([
            'establishment' => $establishment1,
        ]);

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
