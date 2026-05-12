<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\HttpFoundation\Request;

/**
 * Encapsule les paramètres de filtrage et de pagination des avis.
 */
final readonly class ReviewFilterDTO
{
    public const DEFAULT_LIMIT = 10;

    public function __construct(
        public ?int $rating,
        public ?\DateTimeImmutable $publishedSince,
        public int $page,
        public int $limit,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $rating = $request->query->get('rating');
        $period = $request->query->get('period', 'all');
        $page = max(1, (int) $request->query->get('page', 1));

        return new self(
            rating: null !== $rating ? (int) $rating : null,
            publishedSince: self::resolvePeriod($period),
            page: $page,
            limit: self::DEFAULT_LIMIT,
        );
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    private static function resolvePeriod(string $period): ?\DateTimeImmutable
    {
        $days = match ($period) {
            '7j' => 7,
            '30j' => 30,
            '90j' => 90,
            default => null,
        };

        return null !== $days ? new \DateTimeImmutable("-{$days} days") : null;
    }
}
