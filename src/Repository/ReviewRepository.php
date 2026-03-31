<?php

namespace App\Repository;

use App\Entity\Establishment;
use App\Entity\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
}
