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
    public const REPLY_STATUSES = ['unanswered', 'answered'];

    public function __construct(
        public ?int $rating,
        public ?\DateTimeImmutable $publishedSince,
        public int $page,
        public int $limit,
        public ?string $replyStatus = null,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $rating = $request->query->get('rating');
        $rating = is_numeric($rating) && (int) $rating >= 1 && (int) $rating <= 5 ? (int) $rating : null;
        $period = (string) $request->query->get('period', 'all');
        $page = min(10000, max(1, (int) $request->query->get('page', 1)));
        $status = $request->query->get('status');

        return new self(
            rating: $rating,
            publishedSince: self::resolvePeriod($period),
            page: $page,
            limit: self::DEFAULT_LIMIT,
            replyStatus: in_array($status, self::REPLY_STATUSES, true) ? $status : null,
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
