<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\ReviewFilterDTO;
use App\Entity\Establishment;
use App\Entity\Review;
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
            static fn(array $row): array => [
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
}
