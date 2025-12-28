<?php

namespace App\Services;

use App\Models\KnowledgeEmbedding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmbeddingRepository
{
    /**
     * Store a single embedding
     *
     * @param array $embeddingData
     * @return KnowledgeEmbedding|null
     */
    public function store(array $embeddingData): ?KnowledgeEmbedding
    {
        try {
            return KnowledgeEmbedding::createWithVector($embeddingData);
        } catch (\Exception $e) {
            Log::error('Failed to store embedding', [
                'error' => $e->getMessage(),
                'document_id' => $embeddingData['knowledge_document_id'] ?? null,
                'chunk_index' => $embeddingData['chunk_index'] ?? null,
            ]);
            return null;
        }
    }

    /**
     * Store multiple embeddings in batch for better performance
     *
     * @param array $embeddings Array of embedding data
     * @return array Results with success/failure counts
     */
    public function storeBatch(array $embeddings): array
    {
        if (empty($embeddings)) {
            return [
                'success' => true,
                'stored_count' => 0,
                'failed_count' => 0,
                'errors' => []
            ];
        }

        $batchSize = 100; // Process in smaller batches to avoid memory issues
        $totalStored = 0;
        $totalFailed = 0;
        $errors = [];

        // Split embeddings into batches
        $batches = array_chunk($embeddings, $batchSize);

        foreach ($batches as $batch) {
            try {
                $success = KnowledgeEmbedding::batchInsertEmbeddings($batch);

                if ($success) {
                    $totalStored += count($batch);
                } else {
                    $totalFailed += count($batch);
                    $errors[] = "Batch insert failed for " . count($batch) . " embeddings";
                }
            } catch (\Exception $e) {
                $totalFailed += count($batch);
                $errors[] = "Exception in batch insert: " . $e->getMessage();
                Log::error('Batch embedding insert exception', [
                    'error' => $e->getMessage(),
                    'batch_size' => count($batch),
                ]);
            }
        }

        return [
            'success' => $totalFailed === 0,
            'stored_count' => $totalStored,
            'failed_count' => $totalFailed,
            'errors' => $errors
        ];
    }

    /**
     * Store embeddings for a document's chunks
     *
     * @param int $documentId
     * @param int $tenantId
     * @param array $chunks Array of text chunks
     * @param array $embeddings Array of embedding vectors
     * @return array Results
     */
    public function storeDocumentEmbeddings(int $documentId, int $tenantId, array $chunks, array $embeddings): array
    {
        if (count($chunks) !== count($embeddings)) {
            return [
                'success' => false,
                'error' => 'Chunks and embeddings count mismatch',
                'stored_count' => 0,
                'failed_count' => count($chunks)
            ];
        }

        $embeddingData = [];
        foreach ($chunks as $index => $chunk) {
            $embeddingData[] = [
                'tenant_id' => $tenantId,
                'knowledge_document_id' => $documentId,
                'chunk_text' => $chunk,
                'chunk_index' => $index,
                'embedding' => $embeddings[$index]
            ];
        }

        return $this->storeBatch($embeddingData);
    }

    /**
     * Find similar embeddings using vector similarity search
     *
     * @param array $queryEmbedding
     * @param int $tenantId
     * @param int $limit
     * @param float $threshold
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function findSimilar(array $queryEmbedding, int $tenantId, int $limit = 10, float $threshold = 0.0)
    {
        return KnowledgeEmbedding::findSimilar($queryEmbedding, $tenantId, $limit, $threshold);
    }

    /**
     * Delete all embeddings for a document
     *
     * @param int $documentId
     * @param int $tenantId
     * @return int Number of deleted embeddings
     */
    public function deleteDocumentEmbeddings(int $documentId, int $tenantId): int
    {
        try {
            return KnowledgeEmbedding::forTenant($tenantId)
                ->forDocument($documentId)
                ->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete document embeddings', [
                'document_id' => $documentId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Delete all embeddings for a tenant
     *
     * @param int $tenantId
     * @return int Number of deleted embeddings
     */
    public function deleteTenantEmbeddings(int $tenantId): int
    {
        try {
            return KnowledgeEmbedding::forTenant($tenantId)->delete();
        } catch (\Exception $e) {
            Log::error('Failed to delete tenant embeddings', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get embedding statistics for a tenant
     *
     * @param int $tenantId
     * @return array
     */
    public function getTenantStats(int $tenantId): array
    {
        try {
            $stats = KnowledgeEmbedding::forTenant($tenantId)
                ->select([
                    DB::raw('COUNT(*) as total_embeddings'),
                    DB::raw('COUNT(DISTINCT knowledge_document_id) as total_documents'),
                    DB::raw('AVG(LENGTH(chunk_text)) as avg_chunk_length'),
                    DB::raw('MAX(created_at) as latest_embedding')
                ])
                ->first();

            return [
                'total_embeddings' => (int) $stats->total_embeddings,
                'total_documents' => (int) $stats->total_documents,
                'avg_chunk_length' => round((float) $stats->avg_chunk_length, 2),
                'latest_embedding' => $stats->latest_embedding
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get tenant embedding stats', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            return [
                'total_embeddings' => 0,
                'total_documents' => 0,
                'avg_chunk_length' => 0,
                'latest_embedding' => null
            ];
        }
    }

    /**
     * Get embeddings for a specific document
     *
     * @param int $documentId
     * @param int $tenantId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getDocumentEmbeddings(int $documentId, int $tenantId)
    {
        return KnowledgeEmbedding::forTenant($tenantId)
            ->forDocument($documentId)
            ->orderBy('chunk_index')
            ->get();
    }

    /**
     * Update embeddings for a document (delete old, insert new)
     *
     * @param int $documentId
     * @param int $tenantId
     * @param array $chunks
     * @param array $embeddings
     * @return array Results
     */
    public function updateDocumentEmbeddings(int $documentId, int $tenantId, array $chunks, array $embeddings): array
    {
        DB::beginTransaction();

        try {
            // Delete existing embeddings
            $deletedCount = $this->deleteDocumentEmbeddings($documentId, $tenantId);

            // Store new embeddings
            $result = $this->storeDocumentEmbeddings($documentId, $tenantId, $chunks, $embeddings);

            DB::commit();

            return [
                'success' => $result['success'],
                'deleted_count' => $deletedCount,
                'stored_count' => $result['stored_count'],
                'failed_count' => $result['failed_count'],
                'errors' => $result['errors']
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update document embeddings', [
                'document_id' => $documentId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'deleted_count' => 0,
                'stored_count' => 0,
                'failed_count' => count($chunks),
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Perform raw vector similarity search with custom SQL
     *
     * @param array $queryEmbedding
     * @param int $tenantId
     * @param int $limit
     * @param float $threshold
     * @return array Raw results with similarity scores
     */
    public function rawSimilaritySearch(array $queryEmbedding, int $tenantId, int $limit = 10, float $threshold = 0.0): array
    {
        $vectorString = '[' . implode(',', $queryEmbedding) . ']';

        $sql = "
            SELECT
                id,
                knowledge_document_id,
                chunk_text,
                chunk_index,
                1 - (embedding <=> '{$vectorString}'::vector) as similarity
            FROM knowledge_embeddings
            WHERE tenant_id = ?
                AND 1 - (embedding <=> '{$vectorString}'::vector) >= ?
            ORDER BY embedding <=> '{$vectorString}'::vector
            LIMIT ?
        ";

        try {
            $results = DB::select($sql, [$tenantId, $threshold, $limit]);

            return array_map(function ($row) {
                return (array) $row;
            }, $results);

        } catch (\Exception $e) {
            Log::error('Raw similarity search failed', [
                'tenant_id' => $tenantId,
                'limit' => $limit,
                'threshold' => $threshold,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Bulk delete embeddings by IDs
     *
     * @param array $embeddingIds
     * @param int $tenantId
     * @return int Number of deleted embeddings
     */
    public function bulkDelete(array $embeddingIds, int $tenantId): int
    {
        if (empty($embeddingIds)) {
            return 0;
        }

        try {
            return KnowledgeEmbedding::forTenant($tenantId)
                ->whereIn('id', $embeddingIds)
                ->delete();
        } catch (\Exception $e) {
            Log::error('Bulk delete embeddings failed', [
                'embedding_ids' => $embeddingIds,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
}
