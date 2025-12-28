<?php

namespace App\Services;

use App\Models\KnowledgeEmbedding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KnowledgeSearchService
{
    public function __construct(
        private EmbeddingService $embeddingService
    ) {}

    /**
     * Perform semantic search on knowledge base
     *
     * @param string $query The search query
     * @param int $tenantId The tenant ID to scope results
     * @param int $limit Number of results to return (default: 5)
     * @param float $threshold Minimum similarity threshold (0-1, default: 0.0)
     * @return array Search results with similarity scores
     */
    public function search(string $query, int $tenantId, int $limit = 5, float $threshold = 0.0): array
    {
        // Generate embedding for the query
        $queryEmbedding = $this->embeddingService->generateEmbedding($query);

        if (!$queryEmbedding) {
            Log::warning('Failed to generate embedding for search query', [
                'query' => $query,
                'tenant_id' => $tenantId
            ]);
            return [
                'query' => $query,
                'results' => [],
                'total_results' => 0,
                'search_time' => 0,
                'error' => 'Failed to generate query embedding'
            ];
        }

        $startTime = microtime(true);

        // Perform the search using raw SQL for optimal performance
        $results = $this->performVectorSearch($queryEmbedding, $tenantId, $limit, $threshold);

        $searchTime = microtime(true) - $startTime;

        Log::info('Knowledge search completed', [
            'query' => $query,
            'tenant_id' => $tenantId,
            'results_count' => count($results),
            'search_time' => round($searchTime * 1000, 2) . 'ms'
        ]);

        return [
            'query' => $query,
            'results' => $results,
            'total_results' => count($results),
            'search_time' => round($searchTime * 1000, 2),
            'query_embedding' => $queryEmbedding // Include for debugging if needed
        ];
    }

    /**
     * Perform optimized vector similarity search using raw SQL
     *
     * @param array $queryEmbedding
     * @param int $tenantId
     * @param int $limit
     * @param float $threshold
     * @return array
     */
    private function performVectorSearch(array $queryEmbedding, int $tenantId, int $limit, float $threshold): array
    {
        // Convert embedding array to PostgreSQL vector format
        $vectorString = '[' . implode(',', $queryEmbedding) . ']';

        // Raw SQL query using pgvector operators
        // <=> is the cosine distance operator
        // 1 - (embedding <=> query_vector) gives cosine similarity
        $sql = "
            SELECT
                ke.id,
                ke.knowledge_document_id,
                ke.chunk_text,
                ke.chunk_index,
                kd.title as document_title,
                1 - (ke.embedding <=> '{$vectorString}'::vector) as similarity
            FROM knowledge_embeddings ke
            INNER JOIN knowledge_documents kd ON ke.knowledge_document_id = kd.id
            WHERE ke.tenant_id = ?
                AND kd.tenant_id = ?
                AND 1 - (ke.embedding <=> '{$vectorString}'::vector) >= ?
            ORDER BY ke.embedding <=> '{$vectorString}'::vector
            LIMIT ?
        ";

        try {
            $rawResults = DB::select($sql, [$tenantId, $tenantId, $threshold, $limit]);

            // Transform results into a more usable format
            $results = [];
            foreach ($rawResults as $row) {
                $results[] = [
                    'embedding_id' => $row->id,
                    'document_id' => $row->knowledge_document_id,
                    'document_title' => $row->document_title,
                    'chunk_text' => $row->chunk_text,
                    'chunk_index' => $row->chunk_index,
                    'similarity' => round((float) $row->similarity, 4),
                    'similarity_percentage' => round((float) $row->similarity * 100, 2),
                ];
            }

            return $results;

        } catch (\Exception $e) {
            Log::error('Vector search query failed', [
                'tenant_id' => $tenantId,
                'limit' => $limit,
                'threshold' => $threshold,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Search with additional filtering options
     *
     * @param string $query
     * @param int $tenantId
     * @param array $options Additional search options
     * @return array
     */
    public function advancedSearch(string $query, int $tenantId, array $options = []): array
    {
        $limit = $options['limit'] ?? 5;
        $threshold = $options['threshold'] ?? 0.0;
        $documentId = $options['document_id'] ?? null; // Search within specific document

        // Generate embedding for the query
        $queryEmbedding = $this->embeddingService->generateEmbedding($query);

        if (!$queryEmbedding) {
            return [
                'query' => $query,
                'results' => [],
                'total_results' => 0,
                'error' => 'Failed to generate query embedding'
            ];
        }

        $startTime = microtime(true);

        // Perform advanced search
        $results = $this->performAdvancedVectorSearch($queryEmbedding, $tenantId, $limit, $threshold, $documentId);

        $searchTime = microtime(true) - $startTime;

        return [
            'query' => $query,
            'results' => $results,
            'total_results' => count($results),
            'search_time' => round($searchTime * 1000, 2),
            'options' => $options
        ];
    }

    /**
     * Perform advanced vector search with optional document filtering
     */
    private function performAdvancedVectorSearch(array $queryEmbedding, int $tenantId, int $limit, float $threshold, ?int $documentId): array
    {
        $vectorString = '[' . implode(',', $queryEmbedding) . ']';

        $sql = "
            SELECT
                ke.id,
                ke.knowledge_document_id,
                ke.chunk_text,
                ke.chunk_index,
                kd.title as document_title,
                1 - (ke.embedding <=> '{$vectorString}'::vector) as similarity
            FROM knowledge_embeddings ke
            INNER JOIN knowledge_documents kd ON ke.knowledge_document_id = kd.id
            WHERE ke.tenant_id = ?
                AND kd.tenant_id = ?
                AND 1 - (ke.embedding <=> '{$vectorString}'::vector) >= ?
        ";

        $bindings = [$tenantId, $tenantId, $threshold];

        // Add document filter if specified
        if ($documentId) {
            $sql .= " AND ke.knowledge_document_id = ?";
            $bindings[] = $documentId;
        }

        $sql .= "
            ORDER BY ke.embedding <=> '{$vectorString}'::vector
            LIMIT ?
        ";

        $bindings[] = $limit;

        try {
            $rawResults = DB::select($sql, $bindings);

            $results = [];
            foreach ($rawResults as $row) {
                $results[] = [
                    'embedding_id' => $row->id,
                    'document_id' => $row->knowledge_document_id,
                    'document_title' => $row->document_title,
                    'chunk_text' => $row->chunk_text,
                    'chunk_index' => $row->chunk_index,
                    'similarity' => round((float) $row->similarity, 4),
                    'similarity_percentage' => round((float) $row->similarity * 100, 2),
                ];
            }

            return $results;

        } catch (\Exception $e) {
            Log::error('Advanced vector search query failed', [
                'tenant_id' => $tenantId,
                'document_id' => $documentId,
                'limit' => $limit,
                'threshold' => $threshold,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Get search statistics for a tenant
     *
     * @param int $tenantId
     * @return array
     */
    public function getSearchStats(int $tenantId): array
    {
        try {
            $stats = DB::select("
                SELECT
                    COUNT(DISTINCT ke.knowledge_document_id) as total_documents,
                    COUNT(ke.id) as total_embeddings,
                    AVG(1 - (ke.embedding <=> '[0,0,0,0]'::vector)) as avg_similarity,
                    MAX(ke.created_at) as latest_embedding
                FROM knowledge_embeddings ke
                WHERE ke.tenant_id = ?
            ", [$tenantId]);

            return [
                'total_documents' => (int) ($stats[0]->total_documents ?? 0),
                'total_embeddings' => (int) ($stats[0]->total_embeddings ?? 0),
                'latest_embedding' => $stats[0]->latest_embedding ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get search stats', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            return [
                'total_documents' => 0,
                'total_embeddings' => 0,
                'latest_embedding' => null,
            ];
        }
    }

    /**
     * Test search functionality with a simple query
     *
     * @param int $tenantId
     * @return array
     */
    public function testSearch(int $tenantId): array
    {
        $testQuery = "test query for search functionality";

        return $this->search($testQuery, $tenantId, 1, 0.0);
    }
}
