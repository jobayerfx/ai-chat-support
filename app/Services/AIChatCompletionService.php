<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\AIUsageLoggerService;

class AIChatCompletionService
{
    private const OPENAI_BASE_URL = 'https://api.openai.com/v1';
    private const DEFAULT_MODEL = 'gpt-4o-mini';
    private const DEFAULT_TEMPERATURE = 0.2;
    private const DEFAULT_MAX_TOKENS = 500;
    private const MAX_RETRIES = 3;
    private const TIMEOUT_SECONDS = 30;

    // Rate limit cache key prefix
    private const RATE_LIMIT_KEY = 'openai_chat_requests';

    // Maximum requests per minute (adjust based on your OpenAI tier)
    private const MAX_REQUESTS_PER_MINUTE = 1000;

    /**
     * Generate chat completion using OpenAI API with usage logging
     *
     * @param string $prompt The complete prompt
     * @param array $options Additional options (model, temperature, max_tokens, tenant_id, conversation_id)
     * @return string|null The generated response text or null on failure
     */
    public function generateCompletion(string $prompt, array $options = []): ?string
    {
        // Check rate limit
        if (!$this->checkRateLimit()) {
            Log::warning('OpenAI chat completion rate limit exceeded');
            return null;
        }

        $model = $options['model'] ?? $this->getModel();
        $temperature = $options['temperature'] ?? self::DEFAULT_TEMPERATURE;
        $maxTokens = $options['max_tokens'] ?? $this->getMaxTokens();
        $apiKey = $this->getApiKey();

        if (!$apiKey) {
            Log::error('OpenAI API key not configured');
            return null;
        }

        $messages = [
            ['role' => 'user', 'content' => $prompt]
        ];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        $attempt = 0;
        $lastException = null;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])->timeout(self::TIMEOUT_SECONDS)->post(self::OPENAI_BASE_URL . '/chat/completions', $payload);

                // Record the request for rate limiting
                $this->recordRequest();

                if ($response->successful()) {
                    $data = $response->json();

                    if (isset($data['choices'][0]['message']['content'])) {
                        $content = trim($data['choices'][0]['message']['content']);

                        Log::info('OpenAI chat completion successful', [
                            'model' => $model,
                            'temperature' => $temperature,
                            'max_tokens' => $maxTokens,
                            'response_length' => strlen($content),
                            'attempt' => $attempt + 1
                        ]);

                        return $content;
                    }

                    Log::error('Unexpected OpenAI chat completion response format', [
                        'response' => $data
                    ]);
                    return null;
                }

                // Handle specific error codes
                $statusCode = $response->status();
                $errorData = $response->json();

                if ($statusCode === 429) {
                    Log::warning('OpenAI rate limit exceeded', [
                        'retry_after' => $response->header('Retry-After'),
                        'error' => $errorData,
                        'attempt' => $attempt + 1
                    ]);

                    // Exponential backoff
                    $retryAfter = $response->header('Retry-After') ?? (2 ** $attempt);
                    sleep(min((int)$retryAfter, 10)); // Max 10 seconds

                } elseif ($statusCode === 401) {
                    Log::error('OpenAI authentication failed');
                    return null; // Don't retry auth failures

                } elseif ($statusCode === 400) {
                    Log::error('OpenAI bad request', [
                        'error' => $errorData
                    ]);
                    return null; // Don't retry bad requests

                } elseif ($statusCode >= 500) {
                    Log::warning('OpenAI server error, retrying', [
                        'status' => $statusCode,
                        'error' => $errorData,
                        'attempt' => $attempt + 1
                    ]);

                    // Exponential backoff for server errors
                    sleep(2 ** $attempt);

                } else {
                    Log::error('OpenAI API error', [
                        'status' => $statusCode,
                        'error' => $errorData
                    ]);
                    return null;
                }

            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning('Exception during OpenAI chat completion', [
                    'error' => $e->getMessage(),
                    'attempt' => $attempt + 1
                ]);

                // Exponential backoff for exceptions
                sleep(2 ** $attempt);
            }

            $attempt++;
        }

        Log::error('OpenAI chat completion failed after all retries', [
            'attempts' => $attempt,
            'last_error' => $lastException?->getMessage()
        ]);

        return null;
    }

    /**
     * Check if we can make API requests within rate limits
     */
    private function checkRateLimit(): bool
    {
        $key = self::RATE_LIMIT_KEY . '_' . date('Y-m-d-H-i');
        $currentRequests = Cache::get($key, 0);

        return ($currentRequests + 1) <= self::MAX_REQUESTS_PER_MINUTE;
    }

    /**
     * Record API requests for rate limiting
     */
    private function recordRequest(): void
    {
        $key = self::RATE_LIMIT_KEY . '_' . date('Y-m-d-H-i');
        $currentRequests = Cache::get($key, 0);
        Cache::put($key, $currentRequests + 1, 60); // Cache for 60 seconds
    }

    /**
     * Get the OpenAI API key from configuration
     */
    private function getApiKey(): ?string
    {
        return config('services.openai.api_key') ?? env('OPENAI_API_KEY');
    }

    /**
     * Get the model from configuration
     */
    private function getModel(): string
    {
        return config('services.openai.chat_model') ?? env('OPENAI_CHAT_MODEL', self::DEFAULT_MODEL);
    }

    /**
     * Get max tokens from configuration
     */
    private function getMaxTokens(): int
    {
        return config('services.openai.max_tokens') ?? env('OPENAI_MAX_TOKENS', self::DEFAULT_MAX_TOKENS);
    }

    /**
     * Get service health status
     */
    public function getHealthStatus(): array
    {
        $apiKey = $this->getApiKey();
        $model = $this->getModel();
        $maxTokens = $this->getMaxTokens();

        return [
            'service' => 'AIChatCompletionService',
            'configured' => !empty($apiKey),
            'model' => $model,
            'max_tokens' => $maxTokens,
            'temperature' => self::DEFAULT_TEMPERATURE,
            'rate_limit_status' => $this->getRateLimitStatus(),
        ];
    }

    /**
     * Get current rate limit status
     */
    private function getRateLimitStatus(): array
    {
        $key = self::RATE_LIMIT_KEY . '_' . date('Y-m-d-H-i');
        $currentRequests = Cache::get($key, 0);

        return [
            'current_requests' => $currentRequests,
            'max_requests_per_minute' => self::MAX_REQUESTS_PER_MINUTE,
            'remaining_requests' => max(0, self::MAX_REQUESTS_PER_MINUTE - $currentRequests),
            'can_make_request' => $currentRequests < self::MAX_REQUESTS_PER_MINUTE,
        ];
    }

    /**
     * Log AI usage for billing and analytics
     *
     * @param int $tenantId
     * @param int $conversationId
     * @param int $tokensUsed
     * @param string $decision 'ai', 'human', 'ineligible', etc.
     * @return bool Success status
     */
    public function logUsage(int $tenantId, int $conversationId, int $tokensUsed, string $decision = 'ai'): bool
    {
        try {
            $usageLogger = app(AIUsageLoggerService::class);
            return $usageLogger->logUsage($tenantId, $conversationId, $tokensUsed, $decision);
        } catch (\Exception $e) {
            Log::error('Failed to log AI usage in completion service', [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'tokens_used' => $tokensUsed,
                'decision' => $decision,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Estimate tokens used for a prompt and response
     *
     * @param string $prompt
     * @param string $response
     * @return int Estimated token count
     */
    public function estimateTokens(string $prompt, string $response = ''): int
    {
        // Rough estimation: ~4 characters per token for English text
        // This is a simplification - actual tokenization is more complex
        $totalChars = strlen($prompt) + strlen($response);
        return (int)ceil($totalChars / 4);
    }
}
