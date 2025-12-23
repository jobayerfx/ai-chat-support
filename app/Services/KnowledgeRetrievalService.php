<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Contracts\EmbeddingClient;
use App\Models\Tenant;

class KnowledgeRetrievalService
{
    private EmbeddingClient $embeddingClient;
    private int $defaultLimit = 5;
    private float $similarityThreshold = 0.8; // Minimum cosine similarity

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
     * @throws \Exception If no relevant knowledge found
     */
    public function retrieveRelevantKnowledge(Tenant $tenant, string $message, int $limit = null): array
    {
        $limit = $limit ?? $this->defaultLimit;

        try {
            // Generate embedding for user message
            $queryEmbedding = $this->embeddingClient->generateEmbedding($message);

            Log::info('Generated embedding for knowledge retrieval', [
                'tenant_id' => $tenant->id,
                'message_length' => strlen($message),
                'embedding_dimension' => count($queryEmbedding)
            ]);

            // Query pgvector for similar embeddings
            $results = $this->performVectorSearch($tenant->id, $queryEmbedding, $limit);

            if (empty($results)) {
                Log::warning('No knowledge found for query', [
                    'tenant_id' => $tenant->id,
                    'message' => substr($message, 0, 100)
                ]);
                throw new \Exception('No relevant knowledge found for the query');
            }

            // Filter by similarity threshold
            $relevantResults = array_filter($results, function($result) {
                return $result->similarity >= $this->similarityThreshold;
            });

            if (empty($relevantResults)) {
                Log::warning('No knowledge above similarity threshold', [
                    'tenant_id' => $tenant->id,
                    'threshold' => $this->similarityThreshold,
                    'best_similarity' => $results[0]->similarity ?? 0
                ]);
                throw new \Exception('No sufficiently relevant knowledge found');
            }

            $knowledgeChunks = array_map(function($result) {
                return $result->content;
            }, array_slice($relevantResults, 0, $limit));

            Log::info('Retrieved knowledge chunks', [
                'tenant_id' => $tenant->id,
                'chunks_count' => count($knowledgeChunks),
                'best_similarity' => $relevantResults[0]->similarity
            ]);

            return $knowledgeChunks;

        } catch (\Exception $e) {
            Log::error('Knowledge retrieval failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Perform vector similarity search using pgvector
     */
    private function performVectorSearch(int $tenantId, array $queryEmbedding, int $limit): array
    {
        // Convert embedding array to PostgreSQL vector format
        $embeddingString = '[' . implode(',', $queryEmbedding) . ']';

        // pgvector cosine similarity search
        $sql = "
            SELECT
                kd.content,
                1 - (ke.embedding <=> :embedding) as similarity
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
     * Get SQL example for pgvector search
     */
    public static function getPgvectorSearchExample(): string
    {
        return "
-- Example pgvector cosine similarity search
SELECT
    kd.title,
    kd.content,
    1 - (ke.embedding <=> '[0.1,0.2,0.3,...]') as cosine_similarity
FROM knowledge_embeddings ke
JOIN knowledge_documents kd ON ke.knowledge_document_id = kd.id
WHERE ke.tenant_id = 1
ORDER BY ke.embedding <=> '[0.1,0.2,0.3,...]'
LIMIT 5;
        ";
    }
}
