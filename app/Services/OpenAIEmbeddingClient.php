<?php

namespace App\Services;

use App\Contracts\EmbeddingClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIEmbeddingClient implements EmbeddingClient
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.embedding_model', 'text-embedding-ada-002');
    }

    /**
     * Generate embedding vector for the given text
     *
     * @param string $text
     * @return array Vector representation as float array
     * @throws \Exception If embedding generation fails
     */
    public function generateEmbedding(string $text): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/embeddings', [
                'input' => $text,
                'model' => $this->model,
            ]);

            if (!$response->successful()) {
                throw new \Exception('OpenAI API request failed: ' . $response->body());
            }

            $data = $response->json();

            if (!isset($data['data'][0]['embedding'])) {
                throw new \Exception('Invalid response from OpenAI API');
            }

            return $data['data'][0]['embedding'];

        } catch (\Exception $e) {
            Log::error('Embedding generation failed', [
                'error' => $e->getMessage(),
                'text_preview' => substr($text, 0, 100)
            ]);

            throw $e;
        }
    }
}
