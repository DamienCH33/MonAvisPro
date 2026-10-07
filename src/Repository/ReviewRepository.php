<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\ReviewFilterDTO;
use App\Entity\Establishment;
use App\Entity\Review;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /**
     * Récupère une page d'avis pour un établissement, selon un filtre.
     *
     * @return list<Review>
     */
    public function findByFilter(Establishment $establishment, ReviewFilterDTO $filter): array
    {
        return $this->applyFilter($establishment, $filter)
            ->orderBy('r.publishedAt', 'DESC')
            ->setFirstResult($filter->offset())
            ->setMaxResults($filter->limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre total d'avis pour un établissement, selon un filtre.
     */
    public function countByFilter(Establishment $establishment, ReviewFilterDTO $filter): int
    {
        return (int) $this->applyFilter($establishment, $filter)
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Calcule la position d'un avis dans une liste filtrée pour déterminer sa page.
     */
    public function countNewerThan(Review $review, ReviewFilterDTO $filter): int
    {
        $establishment = $review->getEstablishment();
        if (null === $establishment) {
            return 0;
        }

        return (int) $this->applyFilter($establishment, $filter)
            ->select('COUNT(r.id)')
            ->andWhere('r.publishedAt > :targetDate')
            ->setParameter('targetDate', $review->getPublishedAt())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Construit la base de requête commune pour les filtres d'avis.
     */
    private function applyFilter(Establishment $establishment, ReviewFilterDTO $filter): QueryBuilder
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.establishment = :establishment')
            ->setParameter('establishment', $establishment);

        if (null !== $filter->rating) {
            $qb->andWhere('r.rating = :rating')->setParameter('rating', $filter->rating);
        }

        if (null !== $filter->publishedSince) {
            $qb->andWhere('r.publishedAt >= :from')->setParameter('from', $filter->publishedSince);
        }

        if ('unanswered' === $filter->replyStatus) {
            $qb->andWhere('r.ownerReply IS NULL');
        } elseif ('answered' === $filter->replyStatus) {
            $qb->andWhere('r.ownerReply IS NOT NULL');
        }

        return $qb;
    }

    /**
     * @return list<array{
     *     month: string,
     *     average: string,
     *     total: string
     * }>
     */
    public function getAverageRatingByMonth(Establishment $establishment): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
        SELECT 
            TO_CHAR(published_at, 'YYYY-MM') as month,
            ROUND(AVG(rating)::numeric, 1) as average,
            COUNT(id) as total
        FROM review
        WHERE establishment_id = :id
        GROUP BY month
        ORDER BY month ASC
    ";

        $result = $conn->executeQuery($sql, [
            'id' => $establishment->getId(),
        ]);

        $rows = $result->fetchAllAssociative();

        return array_map(
            static fn (array $row): array => [
                'month' => (string) ($row['month'] ?? ''),
                'average' => (string) ($row['average'] ?? '0'),
                'total' => (string) ($row['total'] ?? '0'),
            ],
            $rows
        );
    }

    /**
     * @return list<Review>
     */
    public function findNewReviewsSince(
        Establishment $establishment,
        \DateTimeImmutable $since,
    ): array {
        return $this->createQueryBuilder('r')
            ->where('r.establishment = :establishment')
            ->andWhere('r.publishedAt >= :since')
            ->setParameter('establishment', $establishment)
            ->setParameter('since', $since)
            ->orderBy('r.publishedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Chiffres d'une période pour le bilan mensuel du commerçant.
     *
     * @return array{newCount: int, newAverage: float|null, repliedCount: int, negativeCount: int, totalCount: int, overallAverage: float|null}
     */
    public function getPeriodStats(Establishment $establishment, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        /** @var array{newCount: int|string, newAverage: string|float|null, repliedCount: int|string, negativeCount: int|string} $period */
        $period = $this->createQueryBuilder('r')
            ->select('COUNT(r.id) AS newCount')
            ->addSelect('AVG(r.rating) AS newAverage')
            ->addSelect('SUM(CASE WHEN r.ownerReply IS NOT NULL THEN 1 ELSE 0 END) AS repliedCount')
            ->addSelect('SUM(CASE WHEN r.rating <= 2 THEN 1 ELSE 0 END) AS negativeCount')
            ->where('r.establishment = :establishment')
            ->andWhere('r.publishedAt >= :from')
            ->andWhere('r.publishedAt < :to')
            ->setParameter('establishment', $establishment)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleResult();

        /** @var array{totalCount: int|string, overallAverage: string|float|null} $overall */
        $overall = $this->createQueryBuilder('r')
            ->select('COUNT(r.id) AS totalCount')
            ->addSelect('AVG(r.rating) AS overallAverage')
            ->where('r.establishment = :establishment')
            ->setParameter('establishment', $establishment)
            ->getQuery()
            ->getSingleResult();

        return [
            'newCount' => (int) $period['newCount'],
            'newAverage' => null !== $period['newAverage'] ? round((float) $period['newAverage'], 1) : null,
            'repliedCount' => (int) $period['repliedCount'],
            'negativeCount' => (int) $period['negativeCount'],
            'totalCount' => (int) $overall['totalCount'],
            'overallAverage' => null !== $overall['overallAverage'] ? round((float) $overall['overallAverage'], 1) : null,
        ];
    }

    /**
     * Avis sans réponse de tous les établissements d'un utilisateur (boîte « À traiter »).
     *
     * @return list<Review>
     */
    public function findUnansweredForOwner(User $owner, int $limit = 100): array
    {
        /** @var list<Review> $reviews */
        $reviews = $this->createQueryBuilder('r')
            ->join('r.establishment', 'e')
            ->addSelect('e')
            ->where('e.owner = :owner')
            ->andWhere('r.ownerReply IS NULL')
            ->setParameter('owner', $owner)
            ->orderBy('r.rating', 'ASC')
            ->addOrderBy('r.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $reviews;
    }

    public function countUnansweredForOwner(User $owner): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->join('r.establishment', 'e')
            ->where('e.owner = :owner')
            ->andWhere('r.ownerReply IS NULL')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
