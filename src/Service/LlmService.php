<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class LlmService
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';
    private const MODEL   = 'gpt-4o-mini';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $apiKey,
    ) {}

    /**
     * Analyse thématique des avis d'un établissement
     * Retourne un tableau structuré avec thèmes positifs/négatifs + suggestion
     */
    public function analyzeReviews(array $reviews): ?array
    {
        $reviewsList = implode("\n", array_map(
            fn($r) => sprintf('- Note %d/5 : %s', $r['rating'], $r['text'] ?? '(sans commentaire)'),
            $reviews
        ));

        $prompt = <<<PROMPT
Tu es un expert en réputation en ligne pour les petits commerces français.
Voici les avis Google d'un établissement :

{$reviewsList}

Retourne UNIQUEMENT un JSON valide avec cette structure :
{
  "positive_themes": [
    {"theme": "accueil chaleureux", "percentage": 78, "example": "..."},
    {"theme": "rapidité du service", "percentage": 42, "example": "..."}
  ],
  "negative_themes": [
    {"theme": "temps d'attente", "percentage": 35, "example": "..."}
  ],
  "action_suggestion": "Action concrète en 1 phrase à mettre en place cette semaine"
}
PROMPT;

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'           => self::MODEL,
                    'response_format' => ['type' => 'json_object'],
                    'messages'        => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens'      => 1000,
                    'temperature'     => 0.3,
                ],
            ]);

            $data    = $response->toArray();
            $content = $data['choices'][0]['message']['content'] ?? null;

            if ($content === null) {
                return null;
            }

            return json_decode($content, true);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Génère une réponse professionnelle à un avis
     */
    public function generateReply(string $establishmentName, int $rating, ?string $reviewText, string $tone = 'cordial'): ?string
    {
        $toneLabel = match ($tone) {
            'formel'    => 'formel et professionnel',
            'empathique' => 'empathique et compréhensif',
            default      => 'cordial et chaleureux',
        };

        $prompt = <<<PROMPT
Tu es le gérant de {$establishmentName}.
Un client a laissé cet avis (note : {$rating}/5) :
"{$reviewText}"

Rédige une réponse professionnelle, en français, ton {$toneLabel}.
Maximum 3 phrases. Ne promets rien d'irréaliste.
Retourne uniquement le texte de la réponse, sans guillemets.
PROMPT;

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => self::MODEL,
                    'messages'    => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens'  => 300,
                    'temperature' => 0.7,
                ],
            ]);

            $data = $response->toArray();
            return $data['choices'][0]['message']['content'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
