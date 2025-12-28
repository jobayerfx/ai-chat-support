<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class EmbeddingService
{
    /**
     * OpenAI API base URL
     */
    private const OPENAI_BASE_URL = 'https://api.openai.com/v1';

    /**
     * Default embedding model
     */
    private const DEFAULT_MODEL = 'text-embedding-3-large';

    /**
     * Rate limit cache key prefix
     */
    private const RATE_LIMIT_KEY = 'openai_embedding_requests';

    /**
     * Maximum requests per minute
     */
    private const MAX_REQUESTS_PER_MINUTE = 3000; // OpenAI tier 1 limit

    /**
     * Generate embeddings for text using OpenAI API
     *
     * @param string $text The text to embed
     * @param string|null $model The model to use (default: text-embedding-3-large)
     * @return array|null The embedding vector or null on failure
     */
    public function generateEmbedding(string $text, ?string $model = null): ?array
    {
        // Check rate limit
        if (!$this->checkRateLimit()) {
            Log::warning('OpenAI embedding rate limit exceeded');
            return null;
        }

        $model = $model ?? $this->getModel();
        $apiKey = $this->getApiKey();

        if (!$apiKey) {
            Log::error('OpenAI API key not configured');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(30)->post(self::OPENAI_BASE_URL . '/embeddings', [
                'input' => $text,
                'model' => $model,
                'encoding_format' => 'float',
            ]);

            // Record the request for rate limiting
            $this->recordRequest();

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['data'][0]['embedding'])) {
                    return $data['data'][0]['embedding'];
                }

                Log::error('Unexpected OpenAI response format', [
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
                    'error' => $errorData
                ]);
                $this->handleRateLimitExceeded($response->header('Retry-After'));
            } elseif ($statusCode === 401) {
                Log::error('OpenAI authentication failed');
            } elseif ($statusCode === 400) {
                Log::error('OpenAI bad request', [
                    'error' => $errorData
                ]);
            } else {
                Log::error('OpenAI API error', [
                    'status' => $statusCode,
                    'error' => $errorData
                ]);
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Exception calling OpenAI embeddings API', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text)
            ]);
            return null;
        }
    }

    /**
     * Generate embeddings for multiple texts in batch
     *
     * @param array $texts Array of text strings
     * @param string|null $model The model to use
     * @return array|null Array of embedding vectors or null on failure
     */
    public function generateEmbeddingsBatch(array $texts, ?string $model = null): ?array
    {
        // Check if we can make this batch request
        $batchSize = count($texts);
        if (!$this->checkRateLimit($batchSize)) {
            Log::warning('OpenAI embedding batch rate limit exceeded', [
                'batch_size' => $batchSize
            ]);
            return null;
        }

        $model = $model ?? $this->getModel();
        $apiKey = $this->getApiKey();

        if (!$apiKey) {
            Log::error('OpenAI API key not configured');
            return null;
        }

        // Filter out empty texts and limit batch size
        $texts = array_filter($texts, function ($text) {
            return !empty(trim($text));
        });

        if (empty($texts)) {
            return [];
        }

        // OpenAI has a limit on batch size (typically 2048 inputs)
        $maxBatchSize = 100; // Conservative limit
        if (count($texts) > $maxBatchSize) {
            Log::warning('Batch size too large, truncating', [
                'original_size' => count($texts),
                'max_size' => $maxBatchSize
            ]);
            $texts = array_slice($texts, 0, $maxBatchSize);
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post(self::OPENAI_BASE_URL . '/embeddings', [
                'input' => $texts,
                'model' => $model,
                'encoding_format' => 'float',
            ]);

            // Record the requests for rate limiting
            $this->recordRequest(count($texts));

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['data']) && is_array($data['data'])) {
                    $embeddings = [];
                    foreach ($data['data'] as $item) {
                        $embeddings[] = $item['embedding'] ?? null;
                    }
                    return $embeddings;
                }

                Log::error('Unexpected OpenAI batch response format', [
                    'response' => $data
                ]);
                return null;
            }

            // Handle errors (same as single embedding)
            $statusCode = $response->status();
            $errorData = $response->json();

            if ($statusCode === 429) {
                Log::warning('OpenAI batch rate limit exceeded', [
                    'batch_size' => count($texts),
                    'retry_after' => $response->header('Retry-After'),
                    'error' => $errorData
                ]);
                $this->handleRateLimitExceeded($response->header('Retry-After'));
            } else {
                Log::error('OpenAI batch API error', [
                    'status' => $statusCode,
                    'batch_size' => count($texts),
                    'error' => $errorData
                ]);
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Exception calling OpenAI batch embeddings API', [
                'error' => $e->getMessage(),
                'batch_size' => count($texts)
            ]);
            return null;
        }
    }

    /**
     * Check if we can make API requests within rate limits
     *
     * @param int $requestCount Number of requests to check for
     * @return bool
     */
    private function checkRateLimit(int $requestCount = 1): bool
    {
        $key = self::RATE_LIMIT_KEY . '_' . date('Y-m-d-H-i');
        $currentRequests = Cache::get($key, 0);

        return ($currentRequests + $requestCount) <= self::MAX_REQUESTS_PER_MINUTE;
    }

    /**
     * Record API requests for rate limiting
     *
     * @param int $requestCount Number of requests to record
     */
    private function recordRequest(int $requestCount = 1): void
    {
        $key = self::RATE_LIMIT_KEY . '_' . date('Y-m-d-H-i');
        $currentRequests = Cache::get($key, 0);
        Cache::put($key, $currentRequests + $requestCount, 60); // Cache for 60 seconds
    }

    /**
     * Handle rate limit exceeded scenario
     *
     * @param string|null $retryAfter Retry-After header value
     */
    private function handleRateLimitExceeded(?string $retryAfter): void
    {
        if ($retryAfter) {
            // Implement exponential backoff or queueing logic here
            Log::info('Rate limit retry after', ['seconds' => $retryAfter]);
        }
    }

    /**
     * Get the OpenAI API key from configuration
     *
     * @return string|null
     */
    private function getApiKey(): ?string
    {
        return config('services.openai.api_key') ?? env('OPENAI_API_KEY');
    }

    /**
     * Get the embedding model from configuration
     *
     * @return string
     */
    private function getModel(): string
    {
        return config('services.openai.embedding_model') ?? env('OPENAI_EMBEDDING_MODEL', self::DEFAULT_MODEL);
    }

    /**
     * Calculate similarity between two embedding vectors (cosine similarity)
     *
     * @param array $vectorA
     * @param array $vectorB
     * @return float
     */
    public function calculateSimilarity(array $vectorA, array $vectorB): float
    {
        if (count($vectorA) !== count($vectorB)) {
            throw new \InvalidArgumentException('Vectors must have the same dimensions');
        }

        $dotProduct = 0;
        $magnitudeA = 0;
        $magnitudeB = 0;

        foreach ($vectorA as $i => $valueA) {
            $valueB = $vectorB[$i];
            $dotProduct += $valueA * $valueB;
            $magnitudeA += $valueA * $valueA;
            $magnitudeB += $valueB * $valueB;
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    /**
     * Get service health status
     *
     * @return array
     */
    public function getHealthStatus(): array
    {
        $apiKey = $this->getApiKey();
        $model = $this->getModel();

        return [
            'service' => 'EmbeddingService',
            'configured' => !empty($apiKey),
            'model' => $model,
            'rate_limit_status' => $this->getRateLimitStatus(),
        ];
    }

    /**
     * Get current rate limit status
     *
     * @return array
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
}
