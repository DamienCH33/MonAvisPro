<?php

namespace App\Controller\Api;

use App\Entity\Establishment;
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
    ) {}

    #[Route('/establishments/{id}/analysis', name: 'api_analysis_show', methods: ['GET'])]
    public function show(Establishment $establishment): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $this->denyAccessUnlessOwner($establishment);

        $analysis = $establishment->getReviewAnalysis();

        if ($analysis === null) {
            return $this->json(['message' => 'Aucune analyse disponible.'], 404);
        }

        return $this->json($this->serialize($analysis));
    }

    #[Route('/establishments/{id}/analysis/refresh', name: 'api_analysis_refresh', methods: ['POST'])]
    public function refresh(Establishment $establishment): JsonResponse
    {
        if (!$this->getUser()) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $this->denyAccessUnlessOwner($establishment);

        $analysis = $this->reviewAnalysisService->analyze($establishment);

        if ($analysis === null) {
            return $this->json([
                'error' => 'Impossible de générer l\'analyse. Pas assez d\'avis ou erreur LLM.',
            ], 422);
        }

        return $this->json($this->serialize($analysis));
    }

    #[Route('/reviews/{id}/generate-reply', name: 'api_reviews_generate_reply', methods: ['POST'])]
    public function generateReply(
        \App\Entity\Review $review,
        Request $request
    ): JsonResponse {
        if (!$this->getUser()) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $this->denyAccessUnlessOwner($review->getEstablishment());

        $data = json_decode($request->getContent(), true);
        $tone = $data['tone'] ?? 'cordial';

        if (!in_array($tone, ['cordial', 'formel', 'empathique'])) {
            return $this->json(['error' => 'Ton invalide. Valeurs : cordial, formel, empathique.'], 422);
        }

        $reply = $this->llmService->generateReply(
            $review->getEstablishment()->getName(),
            $review->getRating(),
            $review->getText(),
            $tone
        );

        if ($reply === null) {
            return $this->json(['error' => 'Erreur lors de la génération de la réponse.'], 500);
        }

        return $this->json(['reply' => $reply]);
    }

    private function serialize(\App\Entity\ReviewAnalysis $analysis): array
    {
        return [
            'id'               => $analysis->getId(),
            'positiveThemes'   => $analysis->getPositiveThemes(),
            'negativeThemes'   => $analysis->getNegativeThemes(),
            'actionSuggestion' => $analysis->getActionSuggestion(),
            'updatedAt'        => $analysis->getUpdatedAt()->format('c'),
        ];
    }

    private function denyAccessUnlessOwner(\App\Entity\Establishment $establishment): void
    {
        if ($establishment->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }
    }
}
