<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\ReviewDTO;
use App\Dto\ReviewFilterDTO;
use App\Entity\Establishment;
use App\Entity\Review;
use App\Repository\ReviewRepository;
use App\Service\ReviewReplyService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class ReviewController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReviewRepository $reviewRepository,
        private readonly ReviewReplyService $reviewReplyService,
    ) {
    }

    #[Route('/establishments/{id}/reviews', name: 'api_reviews_list', methods: ['GET'])]
    public function list(Establishment $establishment, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ESTABLISHMENT_OWNER', $establishment);

        $filter = ReviewFilterDTO::fromRequest($request);
        $reviews = $this->reviewRepository->findByFilter($establishment, $filter);
        $total = $this->reviewRepository->countByFilter($establishment, $filter);

        return $this->json([
            'data' => ReviewDTO::collection($reviews),
            'pagination' => [
                'page' => $filter->page,
                'limit' => $filter->limit,
                'total' => $total,
                'totalPages' => (int) ceil($total / $filter->limit),
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
        $sum = array_sum(array_map(static fn (Review $r) => $r->getRating(), $reviews));
        $positive = count(array_filter($reviews, static fn (Review $r) => $r->getRating() >= 4));
        $negative = count(array_filter($reviews, static fn (Review $r) => $r->getRating() <= 2));
        $unread = count(array_filter($reviews, static fn (Review $r) => !$r->isRead()));

        $repartition = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($reviews as $review) {
            ++$repartition[$review->getRating()];
        }

        return $this->json([
            'average' => round($sum / $total, 1),
            'total' => $total,
            'positiveRate' => round(($positive / $total) * 100),
            'negativeRate' => round(($negative / $total) * 100),
            'unreadCount' => $unread,
            'repartition' => $repartition,
            'curve' => $this->reviewRepository->getAverageRatingByMonth($establishment),
        ]);
    }

    /**
     * Trouve la page de pagination où se trouve un avis spécifique.
     */
    #[Route('/reviews/{id}/find-page', name: 'api_review_find_page', methods: ['GET'])]
    public function findPage(Review $review, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ESTABLISHMENT_OWNER', $review->getEstablishment());

        $filter = ReviewFilterDTO::fromRequest($request);
        $position = $this->reviewRepository->countNewerThan($review, $filter);
        $page = (int) ceil(($position + 1) / $filter->limit);

        return $this->json([
            'page' => max(1, $page),
            'reviewId' => (string) $review->getId(),
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
        $reply = is_array($data) ? ($data['reply'] ?? null) : null;

        if (!is_string($reply) || '' === trim($reply)) {
            return $this->json([
                'success' => false,
                'message' => 'Réponse vide',
            ], 400);
        }

        return $this->json($this->reviewReplyService->publish($review, $reply));
    }

    #[Route('/reviews/{id}/reply', name: 'api_review_delete_reply', methods: ['DELETE'])]
    public function deleteReply(Review $review): JsonResponse
    {
        $this->denyAccessUnlessGranted('ESTABLISHMENT_OWNER', $review->getEstablishment());

        return $this->json($this->reviewReplyService->delete($review));
    }
}
