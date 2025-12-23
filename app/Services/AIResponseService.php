<?php

namespace App\Services;

use App\Contracts\LLMClient;
use Illuminate\Support\Facades\Log;

class AIResponseService
{
    private LLMClient $llmClient;

    // Sensitive topic keywords
    private const SENSITIVE_KEYWORDS = [
        'password', 'credit card', 'ssn', 'social security', 'bank account',
        'personal information', 'confidential', 'secret', 'private',
        'financial', 'medical', 'health', 'insurance'
    ];

    public function __construct(LLMClient $llmClient)
    {
        $this->llmClient = $llmClient;
    }

    /**
     * Generate AI response with confidence scoring and sensitivity flagging
     *
     * @param string $prompt Complete prompt for LLM
     * @return array ['response' => string, 'confidence' => float, 'sensitive' => bool]
     */
    public function generateResponse(string $prompt): array
    {
        try {
            $response = $this->llmClient->generate($prompt);

            $confidence = $this->calculateConfidenceScore($response);
            $sensitive = $this->detectSensitiveTopics($response);

            Log::info('AI response generated', [
                'confidence' => $confidence,
                'sensitive' => $sensitive,
                'response_length' => strlen($response)
            ]);

            return [
                'response' => $response,
                'confidence' => $confidence,
                'sensitive' => $sensitive
            ];

        } catch (\Exception $e) {
            Log::error('LLM request failed', ['error' => $e->getMessage()]);

            return [
                'response' => 'I apologize, but I\'m unable to generate a response at this time. Please try again later.',
                'confidence' => 0.0,
                'sensitive' => false
            ];
        }
    }

    /**
     * Calculate confidence score heuristically based on response characteristics
     *
     * @param string $response
     * @return float Confidence score between 0.0 and 1.0
     */
    private function calculateConfidenceScore(string $response): float
    {
        $score = 0.5; // Base score

        $response = strtolower(trim($response));

        // Length-based scoring
        $length = strlen($response);
        if ($length < 10) {
            $score -= 0.3; // Very short responses are suspicious
        } elseif ($length > 50) {
            $score += 0.2; // Longer responses tend to be more confident
        }

        // Keyword-based scoring
        $lowConfidencePhrases = [
            'i don\'t know', 'i\'m not sure', 'i cannot', 'i\'m sorry',
            'no information', 'unable to', 'not available'
        ];

        foreach ($lowConfidencePhrases as $phrase) {
            if (str_contains($response, $phrase)) {
                $score -= 0.2;
                break;
            }
        }

        $highConfidenceIndicators = [
            'according to', 'based on', 'the information', 'as stated',
            'our policy', 'our service', 'we provide'
        ];

        foreach ($highConfidenceIndicators as $indicator) {
            if (str_contains($response, $indicator)) {
                $score += 0.1;
            }
        }

        // Specific data indicators (numbers, dates, etc.)
        if (preg_match('/\d/', $response)) {
            $score += 0.1; // Presence of numbers suggests specific information
        }

        // Structure indicators
        if (str_contains($response, '.') || str_contains($response, '!') || str_contains($response, '?')) {
            $score += 0.1; // Proper punctuation suggests well-formed response
        }

        // Clamp score between 0.0 and 1.0
        return max(0.0, min(1.0, $score));
    }

    /**
     * Detect if response contains sensitive topics
     *
     * @param string $response
     * @return bool
     */
    private function detectSensitiveTopics(string $response): bool
    {
        $lowerResponse = strtolower($response);

        foreach (self::SENSITIVE_KEYWORDS as $keyword) {
            if (str_contains($lowerResponse, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
