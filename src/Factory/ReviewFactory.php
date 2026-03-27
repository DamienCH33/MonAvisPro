<?php

namespace App\Factory;

use App\Entity\Review;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Review>
 */
final class ReviewFactory extends PersistentProxyObjectFactory
{
    private const POSITIVE_REVIEWS = [
        "Excellent service, personnel très accueillant. Je recommande vivement !",
        "Super expérience, on y reviendra sans hésiter. Rapport qualité/prix imbattable.",
        "Accueil chaleureux et professionnel. Tout était parfait du début à la fin.",
        "Très satisfait de ma visite. L'équipe est aux petits soins et très compétente.",
        "Un établissement comme on en trouve peu. Qualité irréprochable, merci !",
        "Rapidité et efficacité au rendez-vous. Personnel souriant et à l'écoute.",
        "Parfait ! Je suis venu sur recommandation et je ne suis pas déçu du tout.",
        "Super endroit, ambiance agréable et prestation de qualité. 5 étoiles méritées.",
    ];

    private const NEGATIVE_REVIEWS = [
        "Attente interminable, plus de 45 minutes sans explication. Décevant.",
        "Service médiocre, personnel peu aimable et peu professionnel.",
        "Mauvaise expérience dans l'ensemble. Je ne reviendrai pas et déconseille.",
        "Qualité en dessous de ce que j'espérais pour le prix demandé.",
        "Personnel pas du tout à l'écoute, sentiment d'être un numéro parmi d'autres.",
        "Le samedi c'est le chaos total, beaucoup trop de monde pour peu de staff.",
    ];

    private const NEUTRAL_REVIEWS = [
        "Correct sans être exceptionnel. Peut mieux faire sur l'accueil.",
        "Service honnête, rien à redire de particulier mais rien d'exceptionnel non plus.",
        "Dans la moyenne. J'attendais peut-être trop au vu des avis positifs.",
    ];

    public static function class(): string
    {
        return Review::class;
    }

    protected function defaults(): array|callable
    {
        $rating = self::faker()->numberBetween(1, 5);

        $text = match (true) {
            $rating >= 4 => self::faker()->randomElement(self::POSITIVE_REVIEWS),
            $rating <= 2 => self::faker()->randomElement(self::NEGATIVE_REVIEWS),
            default      => self::faker()->randomElement(self::NEUTRAL_REVIEWS),
        };

        return [
            'establishment'     => EstablishmentFactory::new(),
            'googleAuthor'      => self::faker()->name(),
            'googleAuthorPhoto' => self::faker()->boolean(70)
                ? 'https://i.pravatar.cc/40?u=' . self::faker()->uuid()
                : null,
            'rating'            => $rating,
            'text'              => self::faker()->boolean(90) ? $text : null,
            'publishedAt'       => self::faker()->dateTimeBetween('-3 months', 'now'),
            'googleReviewId'    => 'ChZI' . self::faker()->unique()->bothify('??##??##??##??##??##'),
            'isRead'            => self::faker()->boolean(40),
        ];
    }
}
