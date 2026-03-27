<?php

namespace App\Factory;

use App\Entity\ReviewAnalysis;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<ReviewAnalysis>
 */
final class ReviewAnalysisFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return ReviewAnalysis::class;
    }

    protected function defaults(): array|callable
    {
        return [
            'establishment'  => EstablishmentFactory::new(),
            'positiveThemes' => [
                ['theme' => 'accueil chaleureux',   'percentage' => self::faker()->numberBetween(50, 85), 'example' => 'Personnel très accueillant'],
                ['theme' => 'rapidité du service',  'percentage' => self::faker()->numberBetween(30, 60), 'example' => 'Service rapide et efficace'],
                ['theme' => 'rapport qualité/prix', 'percentage' => self::faker()->numberBetween(20, 50), 'example' => 'Excellent rapport qualité prix'],
            ],
            'negativeThemes' => [
                ['theme' => "temps d'attente",      'percentage' => self::faker()->numberBetween(20, 45), 'example' => 'Attente trop longue le samedi'],
                ['theme' => 'manque de personnel',  'percentage' => self::faker()->numberBetween(10, 30), 'example' => 'Pas assez de staff en heure de pointe'],
            ],
            'actionSuggestion' => self::faker()->randomElement([
                "5 clients mentionnent l'attente le samedi — envisagez un système de réservation.",
                "Plusieurs avis pointent le manque de communication — formez l'équipe sur l'accueil client.",
                "Le rapport qualité/prix est souvent cité positivement — mettez-le en avant sur votre fiche Google.",
            ]),
            'updatedAt' => self::faker()->dateTimeBetween('-1 week', 'now'),
        ];
    }
}
