<?php

namespace App\Contracts;

/**
 * Interface for LLM client abstraction to avoid vendor lock-in
 */
interface LLMClient
{
    /**
     * Generate response from LLM using the provided prompt
     *
     * @param string $prompt The complete prompt to send to LLM
     * @return string The generated response
     * @throws \Exception If LLM request fails
     */
    public function generate(string $prompt): string;
}
