<?php

namespace App\Controller\Api;

use App\Entity\Establishment;
use App\Entity\Review;
use App\Repository\ReviewRepository;
use App\Service\GoogleBusinessProfileService;
use App\Trait\ReviewSerializerTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ReviewController extends AbstractController
{
    use ReviewSerializerTrait;

    public function __construct(
        private EntityManagerInterface $em,
        private ReviewRepository $reviewRepository,
        private GoogleBusinessProfileService $googleService,
    ) {}

    #[Route('/establishments/{id}/reviews', name: 'api_reviews_list', methods: ['GET'])]
    public function list(Establishment $establishment, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ESTABLISHMENT_OWNER', $establishment);

        $rating = $request->query->get('rating');
        $period = $request->query->get('period', 'all');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        $qb = $this->reviewRepository->createQueryBuilder('r')
            ->where('r.establishment = :establishment')
            ->setParameter('establishment', $establishment)
            ->orderBy('r.publishedAt', 'DESC');

        if (null !== $rating) {
            $qb->andWhere('r.rating = :rating')
                ->setParameter('rating', (int) $rating);
        }

        if ('all' !== $period) {
            $days = match ($period) {
                '7j' => 7,
                '30j' => 30,
                '90j' => 90,
                default => null,
            };

            if (null !== $days) {
                $from = new \DateTimeImmutable("-{$days} days");
                $qb->andWhere('r.publishedAt >= :from')
                    ->setParameter('from', $from);
            }
        }

        $countQb = $this->reviewRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.establishment = :establishment')
            ->setParameter('establishment', $establishment);

        if (null !== $rating) {
            $countQb->andWhere('r.rating = :rating')
                ->setParameter('rating', (int) $rating);
        }

        if ('all' !== $period && isset($from)) {
            $countQb->andWhere('r.publishedAt >= :from')
                ->setParameter('from', $from);
        }

        $total = (int) $countQb->getQuery()->getSingleScalarResult();
        $reviews = $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json([
            'data' => array_map(fn(Review $r) => $this->serialize($r), $reviews),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    #[Route('/establishments/{id}/reviews/stats', name: 'api_reviews_stats', methods: ['GET'])]
    public function stats(Establishment $establishment): JsonResponse
    {
        $this->denyAccessUnlessGranted('ESTABLISHMENT_OWNER', $establishment);

        $reviews = $this->reviewRepository->findBy(['establishment' => $establishment]);

        if (empty($reviews)) {
            return $this->json([
                'average' => null,
                'total' => 0,
                'positiveRate' => 0,
                'negativeRate' => 0,
                'unreadCount' => 0,
                'repartition' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            ]);
        }

        $total = count($reviews);
        $sum = array_sum(array_map(fn(Review $r) => $r->getRating(), $reviews));
        $positive = count(array_filter($reviews, fn(Review $r) => $r->getRating() >= 4));
        $negative = count(array_filter($reviews, fn(Review $r) => $r->getRating() <= 2));
        $unread = count(array_filter($reviews, fn(Review $r) => !$r->isRead()));

        $repartition = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($reviews as $review) {
            ++$repartition[$review->getRating()];
        }

        $curve = $this->reviewRepository->getAverageRatingByMonth($establishment);

        return $this->json([
            'average' => round($sum / $total, 1),
            'total' => $total,
            'positiveRate' => round(($positive / $total) * 100),
            'negativeRate' => round(($negative / $total) * 100),
            'unreadCount' => $unread,
            'repartition' => $repartition,
            'curve' => $curve,
        ]);
    }

    /**
     * Trouve la page de pagination où se trouve un avis spécifique.
     */
    #[Route('/reviews/{id}/find-page', name: 'api_review_find_page', methods: ['GET'])]
    public function findPage(Review $review, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ESTABLISHMENT_OWNER', $review->getEstablishment());

        $limit = 10;
        $rating = $request->query->get('rating');
        $period = $request->query->get('period', 'all');

        // Compter combien d'avis sont publiés AVANT (= plus récents) celui-ci
        $qb = $this->reviewRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.establishment = :establishment')
            ->andWhere('r.publishedAt > :targetDate')
            ->setParameter('establishment', $review->getEstablishment())
            ->setParameter('targetDate', $review->getPublishedAt());

        if (null !== $rating) {
            $qb->andWhere('r.rating = :rating')->setParameter('rating', (int) $rating);
        }

        if ('all' !== $period) {
            $days = match ($period) {
                '7j' => 7,
                '30j' => 30,
                '90j' => 90,
                default => null,
            };

            if (null !== $days) {
                $from = new \DateTimeImmutable("-{$days} days");
                $qb->andWhere('r.publishedAt >= :from')->setParameter('from', $from);
            }
        }

        $position = (int) $qb->getQuery()->getSingleScalarResult();
        $page = (int) ceil(($position + 1) / $limit);

        return $this->json([
            'page' => max(1, $page),
            'reviewId' => $review->getId(),
        ]);
    }

    #[Route('/reviews/{id}/read', name: 'api_reviews_mark_read', methods: ['PATCH'])]
    public function markAsRead(Review $review): JsonResponse
    {
        $this->denyAccessUnlessGranted('ESTABLISHMENT_OWNER', $review->getEstablishment());

        $review->setIsRead(true);
        $this->em->flush();

        return $this->json(['message' => 'Avis marqué comme lu.']);
    }

    #[Route('/reviews/{id}/unread', name: 'api_reviews_mark_unread', methods: ['PATCH'])]
    public function markUnread(Review $review): JsonResponse
    {
        $this->denyAccessUnlessGranted('ESTABLISHMENT_OWNER', $review->getEstablishment());

        $review->setIsRead(false);
        $this->em->flush();

        return $this->json(['message' => 'Avis marqué comme non lu']);
    }

    #[Route('/reviews/{id}/reply', name: 'api_review_reply', methods: ['POST'])]
    public function reply(Review $review, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ESTABLISHMENT_OWNER', $review->getEstablishment());

        $data = json_decode($request->getContent(), true);
        $reply = $data['reply'] ?? null;

        if (!$reply) {
            return $this->json([
                'success' => false,
                'message' => 'Réponse vide',
            ], 400);
        }

        $establishment = $review->getEstablishment();
        $review->setOwnerReply($reply);

        // Publier sur Google si OAuth configuré
        if ($establishment->getGoogleAccessToken() && $review->getGoogleReviewName()) {
            try {
                $now = new \DateTimeImmutable();
                if ($establishment->getGoogleTokenExpiresAt() < $now) {
                    $tokenData = $this->googleService->refreshAccessToken(
                        $establishment->getGoogleRefreshToken()
                    );
                    $establishment->setGoogleAccessToken($tokenData['access_token']);
                    $establishment->setGoogleTokenExpiresAt(
                        (new \DateTimeImmutable())->modify('+' . ($tokenData['expires_in'] ?? 3600) . ' seconds')
                    );
                }

                $this->googleService->publishReply(
                    $review->getGoogleReviewName(),
                    $reply,
                    $establishment->getGoogleAccessToken()
                );

                $review->setIsPublishedToGoogle(true);
                $review->setGoogleReplyPublishedAt(new \DateTimeImmutable());
            } catch (\Exception $e) {
                $this->em->flush();
                return $this->json([
                    'success' => true,
                    'warning' => 'Réponse sauvegardée localement mais non publiée sur Google : ' . $e->getMessage()
                ]);
            }
        }

        $this->em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/reviews/{id}/reply', name: 'api_review_delete_reply', methods: ['DELETE'])]
    public function deleteReply(Review $review): JsonResponse
    {
        $this->denyAccessUnlessGranted('ESTABLISHMENT_OWNER', $review->getEstablishment());

        $establishment = $review->getEstablishment();

        if (
            $review->isPublishedToGoogle()
            && $establishment->getGoogleAccessToken()
            && $review->getGoogleReviewName()
        ) {
            try {
                $now = new \DateTimeImmutable();
                if ($establishment->getGoogleTokenExpiresAt() < $now) {
                    $tokenData = $this->googleService->refreshAccessToken(
                        $establishment->getGoogleRefreshToken()
                    );
                    $establishment->setGoogleAccessToken($tokenData['access_token']);
                    $establishment->setGoogleTokenExpiresAt(
                        (new \DateTimeImmutable())->modify('+' . ($tokenData['expires_in'] ?? 3600) . ' seconds')
                    );
                }

                $this->googleService->deleteReply(
                    $review->getGoogleReviewName(),
                    $establishment->getGoogleAccessToken()
                );
            } catch (\Exception $e) {
                $review->setOwnerReply(null);
                $review->setIsPublishedToGoogle(false);
                $review->setGoogleReplyPublishedAt(null);
                $this->em->flush();

                return $this->json([
                    'success' => true,
                    'warning' => 'Réponse supprimée localement mais erreur Google : ' . $e->getMessage()
                ]);
            }
        }

        $review->setOwnerReply(null);
        $review->setIsPublishedToGoogle(false);
        $review->setGoogleReplyPublishedAt(null);

        $this->em->flush();

        return $this->json(['success' => true]);
    }
}
