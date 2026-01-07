<?php

namespace App\Services;

use App\Contracts\LLMClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAILLMClient implements LLMClient
{
    private string $apiKey;
    private string $model;
    private int $maxTokens;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
        $this->model = config('services.openai.chat_model', 'gpt-4o-mini');
        $this->maxTokens = config('services.openai.max_tokens', 500);
    }

    /**
     * Generate response from LLM using the provided prompt
     *
     * @param string $prompt The complete prompt to send to LLM
     * @return string The generated response
     * @throws \Exception If LLM request fails
     */
    public function generate(string $prompt): string
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => $this->maxTokens,
                'temperature' => 0.7,
            ]);

            if (!$response->successful()) {
                throw new \Exception('OpenAI API request failed: ' . $response->body());
            }

            $data = $response->json();

            if (!isset($data['choices'][0]['message']['content'])) {
                throw new \Exception('Invalid response from OpenAI API');
            }

            return trim($data['choices'][0]['message']['content']);

        } catch (\Exception $e) {
            Log::error('LLM generation failed', [
                'error' => $e->getMessage(),
                'prompt_preview' => substr($prompt, 0, 100)
            ]);

            throw $e;
        }
    }
}
