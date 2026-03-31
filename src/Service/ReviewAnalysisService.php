<?php

namespace App\Service;

use App\Entity\Establishment;
use App\Entity\ReviewAnalysis;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;

class ReviewAnalysisService
{
    public function __construct(
        private LlmService $llmService,
        private ReviewRepository $reviewRepository,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Lance ou relance l'analyse LLM pour un établissement.
     */
    public function analyze(Establishment $establishment): ?ReviewAnalysis
    {
        $reviews = $this->reviewRepository->findBy([
            'establishment' => $establishment,
        ]);

        $reviewsData = array_filter(
            array_map(
                static fn ($r): array => [
                    'rating' => $r->getRating(),
                    'text' => $r->getText(),
                ],
                $reviews
            ),
            static fn (array $r): bool => !empty($r['text'])
        );

        if (empty($reviewsData)) {
            return null;
        }

        $result = $this->llmService->analyzeReviews(array_values($reviewsData));

        if (null === $result) {
            return null;
        }

        $analysis = $establishment->getReviewAnalysis();

        if (null === $analysis) {
            $analysis = new ReviewAnalysis();
            $analysis->setEstablishment($establishment);
            $this->em->persist($analysis);
        }

        $positiveThemes = array_map(
            static fn (array $theme): string => $theme['theme'],
            $result['positive_themes']
        );

        $negativeThemes = array_map(
            static fn (array $theme): string => $theme['theme'],
            $result['negative_themes']
        );

        $analysis->setPositiveThemes($positiveThemes);
        $analysis->setNegativeThemes($negativeThemes);
        $analysis->setActionSuggestion($result['action_suggestion']);
        $analysis->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();

        return $analysis;
    }
}
