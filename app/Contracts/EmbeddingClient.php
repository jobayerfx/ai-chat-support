<?php

namespace App\Contracts;

/**
 * Interface for embedding client to generate vector representations
 */
interface EmbeddingClient
{
    /**
     * Generate embedding vector for the given text
     *
     * @param string $text
     * @return array Vector representation as float array
     * @throws \Exception If embedding generation fails
     */
    public function generateEmbedding(string $text): array;
}
