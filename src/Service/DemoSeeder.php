<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Establishment;
use App\Entity\Review;
use App\Entity\ReviewAnalysis;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Remplit le compte de démonstration avec des données crédibles :
 * deux commerces, des avis français dont le texte correspond à la note,
 * quelques réponses déjà publiées et une analyse.
 *
 * Utilisé par les fixtures et par la commande app:demo:reset (remise à zéro quotidienne).
 */
final class DemoSeeder
{
    /**
     * [auteur, note, il y a N jours, texte|null, réponse|null].
     *
     * @var list<array{0: string, 1: int, 2: int, 3: string|null, 4: string|null}>
     */
    private const BAKERY_REVIEWS = [
        ['François Morel', 1, 3, 'Attente interminable, plus de 45 minutes pour récupérer une commande réservée depuis deux semaines. Personne ne nous a prévenus du retard. Décevant.', null],
        ['Patrick Blanc', 1, 9, 'Baguette pas cuite deux jours de suite, et la vendeuse a haussé les épaules quand je l\'ai signalé. Je vais ailleurs.', null],
        ['Isabelle Faure', 2, 6, 'Les viennoiseries sont bonnes mais le service est très lent le dimanche matin, une seule personne en caisse pour toute la file.', null],
        ['Valérie Simon', 3, 8, 'Le pain est bon, mais l\'accueil le dimanche matin manque de sourire.', null],
        ['Michel Laurent', 3, 15, 'Correct. Les prix ont pas mal augmenté depuis la rentrée.', null],
        ['Camille Roux', 5, 1, 'La meilleure tradition du quartier, croûte croustillante et mie bien alvéolée. Et toujours un mot gentil.', null],
        ['Hugo Lefebvre', 4, 4, 'Très bons flans et pain aux céréales. Un peu d\'attente à 18 h mais ça vaut le coup.', null],
        ['Nadia Benali', 5, 2, null, null],
        ['Julien Mercier', 5, 12, 'Commande de 40 mini-viennoiseries pour un séminaire : prêtes à l\'heure, parfaites. Merci !', null],
        ['Sophie Garnier', 5, 20, 'Le gâteau d\'anniversaire était magnifique et délicieux. Les enfants ont adoré.', 'Merci beaucoup Sophie ! Toute l\'équipe est ravie que le gâteau ait plu aux enfants. À très bientôt.'],
        ['Antoine Perrin', 4, 27, 'Bon pain, bonnes pâtisseries, équipe sympathique.', 'Merci Antoine pour votre fidélité, à bientôt !'],
        ['Élodie Chevalier', 2, 33, 'Éclair au café un peu sec, dommage vu le prix.', 'Bonjour Élodie, merci pour ce retour. Nous avons revu la recette de la crème avec notre pâtissier. Nous espérons vous faire changer d\'avis lors de votre prochaine visite.'],
        ['Thomas Dubois', 5, 41, 'Croissants au beurre exceptionnels, on sent la qualité.', 'Merci Thomas, nous transmettons au fournil !'],
        ['Laura Fontaine', 5, 55, 'Accueil adorable et pain toujours chaud le matin.', 'Merci Laura, à demain matin alors !'],
        ['Marc Girard', 4, 62, 'Très bonne boulangerie, je recommande la fougasse.', 'Merci Marc !'],
        ['Chloé Martin', 5, 80, 'Ma boulangerie préférée depuis que j\'habite le quartier.', 'Merci Chloé, c\'est un plaisir de vous servir.'],
    ];

    /** @var list<array{0: string, 1: int, 2: int, 3: string|null, 4: string|null}> */
    private const SALON_REVIEWS = [
        ['Philippe Garnier', 1, 5, 'Rendez-vous à 10 h, pris en charge à 10 h 40. Le samedi c\'est le chaos, beaucoup trop de monde pour peu de coiffeurs.', null],
        ['Christine Petit', 2, 7, 'Couleur pas du tout celle demandée. On m\'a proposé de revenir, mais sans s\'excuser.', null],
        ['Romain Lefèvre', 3, 10, 'Coupe correcte, rien d\'exceptionnel. Un peu cher pour un dégradé simple.', null],
        ['Alexandre Morin', 3, 14, null, null],
        ['Manon Guerin', 5, 2, 'Pauline a parfaitement compris ce que je voulais. Ambiance calme, vraiment zen comme le nom l\'indique.', null],
        ['Thomas Bonnet', 4, 4, 'Super expérience, bon conseil sur l\'entretien des cheveux bouclés.', null],
        ['Léa Rousseau', 5, 18, 'Balayage magnifique, je suis ravie. Merci !', 'Merci Léa, ravies que le résultat vous plaise. À bientôt au salon !'],
        ['Sarah Lambert', 5, 26, 'Le massage crânien pendant le shampoing, un vrai moment de détente.', 'Merci Sarah, c\'est notre petit plus !'],
        ['Nicolas Henry', 4, 37, 'Rapide et efficace, prix raisonnables.', 'Merci Nicolas, à la prochaine !'],
        ['Inès Robin', 2, 45, 'Attente de 20 minutes malgré le rendez-vous.', 'Bonjour Inès, toutes nos excuses pour cette attente. Nous avons ajouté un créneau tampon le samedi pour éviter les retards.'],
        ['Emma Faure', 5, 60, 'Toujours un plaisir, équipe aux petits soins.', 'Merci Emma !'],
        ['Lucas Moreau', 4, 75, 'Bonne coupe, je reviendrai.', 'Merci Lucas, à bientôt.'],
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function reset(User $demo): void
    {
        $this->em->createQuery('DELETE FROM '.Review::class.' r WHERE r.establishment IN (SELECT e.id FROM '.Establishment::class.' e WHERE e.owner = :owner)')
            ->setParameter('owner', $demo)
            ->execute();
        $this->em->createQuery('DELETE FROM '.ReviewAnalysis::class.' a WHERE a.establishment IN (SELECT e.id FROM '.Establishment::class.' e WHERE e.owner = :owner)')
            ->setParameter('owner', $demo)
            ->execute();
        $this->em->createQuery('DELETE FROM '.Establishment::class.' e WHERE e.owner = :owner')
            ->setParameter('owner', $demo)
            ->execute();
        $demoId = $demo->getId();
        $this->em->clear();

        $demo = $this->em->find(User::class, $demoId);
        if (null !== $demo) {
            $this->seed($demo);
        }
    }

    public function seed(User $demo): void
    {
        $now = new \DateTimeImmutable();

        $bakery = (new Establishment())
            ->setOwner($demo)
            ->setName('Boulangerie du Coin')
            ->setAddress('14 cours Victor Hugo, 33000 Bordeaux')
            ->setPlaceId('ChIJdemoBoulangerieDuCoin01')
            ->setAlertsEnabled(true)
            ->setLastSyncAt($now->modify('-2 hours'))
            ->setCreatedAt($now->modify('-4 months'))
            ->setReplyFormality('vous')
            ->setReplyTone('cordial')
            ->setReplySignature('L\'équipe de la Boulangerie du Coin')
            ->setReplyInstructions('Rappeler que la boutique est fermée le lundi. Ne jamais promettre de remboursement par écrit.');

        $salon = (new Establishment())
            ->setOwner($demo)
            ->setName('Salon Coiffure Zen')
            ->setAddress('22 rue Sainte-Catherine, 33000 Bordeaux')
            ->setPlaceId('ChIJdemoSalonCoiffureZen02')
            ->setAlertsEnabled(true)
            ->setLastSyncAt($now->modify('-3 hours'))
            ->setCreatedAt($now->modify('-3 months'))
            ->setReplyFormality('vous')
            ->setReplyTone('empathique')
            ->setReplySignature('Pauline et l\'équipe du Salon Zen');

        $this->em->persist($bakery);
        $this->em->persist($salon);

        $this->addReviews($bakery, self::BAKERY_REVIEWS, $now);
        $this->addReviews($salon, self::SALON_REVIEWS, $now);

        $analysis = (new ReviewAnalysis())
            ->setEstablishment($bakery)
            ->setPositiveThemes([
                ['theme' => 'qualité du pain', 'percentage' => 62, 'example' => 'La meilleure tradition du quartier'],
                ['theme' => 'pâtisseries et commandes', 'percentage' => 38, 'example' => 'Le gâteau d\'anniversaire était magnifique'],
                ['theme' => 'accueil', 'percentage' => 25, 'example' => 'Accueil adorable et pain toujours chaud'],
            ])
            ->setNegativeThemes([
                ['theme' => 'attente le week-end', 'percentage' => 25, 'example' => 'Une seule personne en caisse pour toute la file'],
                ['theme' => 'retards de commande', 'percentage' => 12, 'example' => 'Personne ne nous a prévenus du retard'],
            ])
            ->setActionSuggestion('L\'attente du samedi et du dimanche revient dans 4 avis : ouvrir une deuxième caisse aux heures de pointe, et prévenir par SMS en cas de retard sur une commande.')
            ->setUpdatedAt($now->modify('-1 day'));
        $this->em->persist($analysis);

        $this->em->flush();
    }

    /**
     * @param list<array{0: string, 1: int, 2: int, 3: string|null, 4: string|null}> $rows
     */
    private function addReviews(Establishment $establishment, array $rows, \DateTimeImmutable $now): void
    {
        foreach ($rows as $i => [$author, $rating, $daysAgo, $text, $reply]) {
            $publishedAt = $now->modify(sprintf('-%d days -%d hours', $daysAgo, ($i * 5) % 11));

            $review = (new Review())
                ->setEstablishment($establishment)
                ->setGoogleAuthor($author)
                ->setRating($rating)
                ->setText($text)
                ->setPublishedAt($publishedAt)
                ->setGoogleReviewId(sprintf('demo-%s-%02d', substr((string) $establishment->getPlaceId(), -2), $i))
                ->setIsRead(null !== $reply || $daysAgo > 10);

            if (null !== $reply) {
                $review->setOwnerReply($reply)
                    ->setIsPublishedToGoogle(true)
                    ->setGoogleReplyPublishedAt($publishedAt->modify('+1 day'));
            }

            $this->em->persist($review);
        }
    }
}
