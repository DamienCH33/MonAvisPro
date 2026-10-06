<?php

namespace App\Controller\Api;

use App\Entity\Establishment;
use App\Entity\Review;
use App\Entity\ReviewAnalysis;
use App\Service\LlmService;
use App\Service\ReviewAnalysisService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api')]
class AnalysisController extends AbstractController
{
    public function __construct(
        private ReviewAnalysisService $reviewAnalysisService,
        private LlmService $llmService,
    ) {
    }

    #[Route('/establishments/{id}/analysis', name: 'api_analysis_show', methods: ['GET'])]
    public function show(Establishment $establishment): JsonResponse
    {
        $this->denyAccessUnlessGranted('ESTABLISHMENT_OWNER', $establishment);

        $analysis = $establishment->getReviewAnalysis();

        if (null === $analysis) {
            return $this->json(['message' => 'Aucune analyse disponible.'], 404);
        }

        return $this->json($this->serialize($analysis));
    }

    #[Route('/establishments/{id}/analysis/refresh', name: 'api_analysis_refresh', methods: ['POST'])]
    public function refresh(Establishment $establishment): JsonResponse
    {
        $this->denyAccessUnlessGranted('ESTABLISHMENT_OWNER', $establishment);

        $analysis = $this->reviewAnalysisService->analyze($establishment);

        if (null === $analysis) {
            return $this->json([
                'error' => 'Impossible de générer l\'analyse.',
            ], 422);
        }

        return $this->json($this->serialize($analysis));
    }

    #[Route('/reviews/{id}/generate-reply', name: 'api_reviews_generate_reply', methods: ['POST'])]
    public function generateReply(Review $review, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ESTABLISHMENT_OWNER', $review->getEstablishment());

        $establishment = $review->getEstablishment();
        if (null === $establishment) {
            return $this->json(['error' => 'Avis sans établissement.'], 422);
        }

        $data = json_decode($request->getContent(), true);
        $tone = is_array($data) && isset($data['tone']) ? $data['tone'] : $establishment->getReplyTone();

        if (!in_array($tone, Establishment::TONES, true)) {
            return $this->json(['error' => 'Ton invalide.'], 422);
        }

        $reply = $this->llmService->generateReply(
            (string) $establishment->getName(),
            (int) $review->getRating(),
            $review->getText(),
            $tone,
            $establishment->getReplyFormality(),
            $establishment->getReplySignature(),
            $establishment->getReplyInstructions(),
            $review->getGoogleAuthor(),
        );

        if (null === $reply) {
            return $this->json(['error' => 'Erreur génération'], 500);
        }

        return $this->json(['reply' => $reply]);
    }

    /**
     * @return array{
     *     id: string|null,
     *     positiveThemes: list<string>,
     *     negativeThemes: list<string>,
     *     actionSuggestion: string|null,
     *     updatedAt: string
     * }
     */
    private function serialize(ReviewAnalysis $analysis): array
    {
        return [
            'id' => $analysis->getId()?->toRfc4122(),

            'positiveThemes' => array_values(
                array_map(fn ($t) => $t['theme'], $analysis->getPositiveThemes())
            ),

            'negativeThemes' => array_values(
                array_map(fn ($t) => $t['theme'], $analysis->getNegativeThemes())
            ),

            'actionSuggestion' => $analysis->getActionSuggestion(),
            'updatedAt' => $analysis->getUpdatedAt()?->format('c') ?? '',
        ];
    }
}
