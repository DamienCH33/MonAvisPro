<?php

namespace App\Factory;

use App\Entity\Establishment;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Establishment>
 */
final class EstablishmentFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Establishment::class;
    }

    protected function defaults(): array|callable
    {
        $establishments = [
            ['name' => 'Boulangerie Dupont',     'address' => '12 rue de la Paix, 75001 Paris'],
            ['name' => 'Restaurant La Fontaine', 'address' => '5 place du Capitole, 31000 Toulouse'],
            ['name' => 'Salon Coiffure Élodie',  'address' => '8 avenue Gambetta, 69003 Lyon'],
            ['name' => 'Pizzeria Napoli',         'address' => '23 rue du Port, 13002 Marseille'],
            ['name' => 'Cabinet Kiné Sport',      'address' => '3 boulevard Victor Hugo, 06000 Nice'],
            ['name' => 'Garage Martin Auto',      'address' => '47 route de Bordeaux, 33000 Bordeaux'],
            ['name' => 'Pharmacie Centrale',      'address' => '1 rue Nationale, 59000 Lille'],
        ];

        $pick = self::faker()->randomElement($establishments);

        return [
            'owner' => UserFactory::new(),
            'name' => $pick['name'],
            'placeId' => 'ChIJ'.strtoupper(self::faker()->bothify('??##??##??##??##')),
            'address' => $pick['address'],
            'alertsEnabled' => self::faker()->boolean(75),
            'lastSyncAt' => self::faker()->optional(0.8)->dateTimeBetween('-2 days', 'now'),
            'createdAt' => self::faker()->dateTimeBetween('-3 months', '-1 week'),
        ];
    }
}
