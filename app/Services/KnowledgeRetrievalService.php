<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Contracts\EmbeddingClient;
use App\Models\Tenant;

class KnowledgeRetrievalService
{
    private EmbeddingClient $embeddingClient;
    private int $defaultLimit = 3;

    public function __construct(EmbeddingClient $embeddingClient)
    {
        $this->embeddingClient = $embeddingClient;
    }

    /**
     * Retrieve relevant knowledge chunks using semantic search
     *
     * @param Tenant $tenant
     * @param string $message
     * @param int $limit
     * @return array Array of knowledge content strings
     */
    public function retrieveRelevantKnowledge(Tenant $tenant, string $message, int $limit = null): array
    {
        $limit = $limit ?? $this->defaultLimit;

        try {
            // Generate embedding for user message
            $queryEmbedding = $this->embeddingClient->generateEmbedding($message);

            if (!$queryEmbedding) {
                Log::error('Failed to generate embedding for message', [
                    'tenant_id' => $tenant->id,
                    'message_length' => strlen($message)
                ]);
                return [];
            }

            Log::info('Generated embedding for knowledge retrieval', [
                'tenant_id' => $tenant->id,
                'message_length' => strlen($message),
                'embedding_dimension' => count($queryEmbedding)
            ]);

            // Query pgvector for top similar embeddings
            $results = $this->performVectorSearch($tenant->id, $queryEmbedding, $limit);

            // Extract text snippets only
            $knowledgeChunks = array_map(function($result) {
                return $result->content;
            }, $results);

            Log::info('Retrieved knowledge chunks', [
                'tenant_id' => $tenant->id,
                'chunks_count' => count($knowledgeChunks)
            ]);

            return $knowledgeChunks;

        } catch (\Exception $e) {
            Log::error('Knowledge retrieval failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Perform vector similarity search using pgvector
     * Returns top matches ordered by cosine similarity (closest first)
     */
    private function performVectorSearch(int $tenantId, array $queryEmbedding, int $limit): array
    {
        // Convert embedding array to PostgreSQL vector format
        $embeddingString = '[' . implode(',', $queryEmbedding) . ']';

        // Optimized pgvector cosine similarity search
        // <=> operator calculates cosine distance (1 - cosine similarity)
        // Lower distance = higher similarity, so we order by distance ascending
        $sql = "
            SELECT kd.content
            FROM knowledge_embeddings ke
            JOIN knowledge_documents kd ON ke.knowledge_document_id = kd.id
            WHERE ke.tenant_id = :tenant_id
            ORDER BY ke.embedding <=> :embedding
            LIMIT :limit
        ";

        return DB::select($sql, [
            'embedding' => $embeddingString,
            'tenant_id' => $tenantId,
            'limit' => $limit
        ]);
    }

    /**
     * Get optimized SQL example for pgvector search (top 3 matches)
     */
    public static function getPgvectorSearchExample(): string
    {
        return "
-- Optimized pgvector cosine similarity search for top 3 matches
-- Returns only text snippets, tenant-scoped, ordered by similarity
SELECT kd.content
FROM knowledge_embeddings ke
JOIN knowledge_documents kd ON ke.knowledge_document_id = kd.id
WHERE ke.tenant_id = :tenant_id
ORDER BY ke.embedding <=> :embedding
LIMIT 3;
        ";
    }
}
