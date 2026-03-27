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
    ) {}

    /**
     * Lance ou relance l'analyse LLM pour un établissement
     */
    public function analyze(Establishment $establishment): ?ReviewAnalysis
    {
        $reviews = $this->reviewRepository->findBy(['establishment' => $establishment]);

        $reviewsData = array_filter(
            array_map(fn($r) => [
                'rating' => $r->getRating(),
                'text'   => $r->getText(),
            ], $reviews),
            fn($r) => !empty($r['text'])
        );

        if (empty($reviewsData)) {
            return null;
        }

        $result = $this->llmService->analyzeReviews(array_values($reviewsData));

        if ($result === null) {
            return null;
        }

        $analysis = $establishment->getReviewAnalysis();

        if ($analysis === null) {
            $analysis = new ReviewAnalysis();
            $analysis->setEstablishment($establishment);
            $this->em->persist($analysis);
        }

        $analysis->setPositiveThemes($result['positive_themes'] ?? []);
        $analysis->setNegativeThemes($result['negative_themes'] ?? []);
        $analysis->setActionSuggestion($result['action_suggestion'] ?? '');
        $analysis->setUpdatedAt(new \DateTimeImmutable());

        $this->em->flush();

        return $analysis;
    }
}
