<?php

namespace App\Controller\Api;

use App\Entity\Establishment;
use App\Entity\Review;
use App\Repository\ReviewRepository;
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
                'message' => 'Réponse vide'
            ], 400);
        }

        $review->setOwnerReply($reply);
        $this->em->flush();

        return $this->json([
            'success' => true
        ]);
    }

    #[Route('/reviews/{id}/reply', name: 'api_review_delete_reply', methods: ['DELETE'])]
    public function deleteReply(Review $review): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            'ESTABLISHMENT_OWNER',
            $review->getEstablishment()
        );

        $review->setOwnerReply(null);
        $this->em->flush();

        return $this->json([
            'success' => true
        ]);
    }
}
