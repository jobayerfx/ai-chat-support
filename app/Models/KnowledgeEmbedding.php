<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class KnowledgeEmbedding extends Model
{
    use HasFactory;

    protected $fillable = [
        'knowledge_document_id',
        'tenant_id',
        'chunk_text',
        'chunk_index',
        'embedding'
    ];

    protected $casts = [
        'embedding' => 'array', // Cast JSON to array for pgvector operations
    ];

    /**
     * Get the knowledge document that owns this embedding.
     */
    public function knowledgeDocument()
    {
        return $this->belongsTo(KnowledgeDocument::class);
    }

    /**
     * Get the tenant that owns this embedding.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to filter by tenant
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope to filter by knowledge document
     */
    public function scopeForDocument($query, $documentId)
    {
        return $query->where('knowledge_document_id', $documentId);
    }

    /**
     * Find similar embeddings using cosine similarity
     *
     * @param array $queryEmbedding The query embedding vector
     * @param int $tenantId The tenant ID to scope results
     * @param int $limit Number of results to return
     * @param float $threshold Minimum similarity threshold (0-1)
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function findSimilar(array $queryEmbedding, int $tenantId, int $limit = 10, float $threshold = 0.0)
    {
        // Convert embedding array to PostgreSQL vector format
        $vectorString = '[' . implode(',', $queryEmbedding) . ']';

        return static::select([
                'knowledge_embeddings.*',
                DB::raw("1 - (embedding <=> '{$vectorString}'::vector) as similarity")
            ])
            ->where('tenant_id', $tenantId)
            ->having('similarity', '>=', $threshold)
            ->orderBy('similarity', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Create embedding with raw SQL for better performance
     *
     * @param array $data Embedding data
     * @return static
     */
    public static function createWithVector(array $data): static
    {
        // Ensure embedding is properly formatted
        if (isset($data['embedding']) && is_array($data['embedding'])) {
            $vectorString = '[' . implode(',', $data['embedding']) . ']';
            $data['embedding'] = DB::raw("'{$vectorString}'::vector");
        }

        return static::create($data);
    }

    /**
     * Batch insert embeddings for better performance
     *
     * @param array $embeddings Array of embedding data
     * @return bool
     */
    public static function batchInsertEmbeddings(array $embeddings): bool
    {
        if (empty($embeddings)) {
            return true;
        }

        $values = [];
        $bindings = [];

        foreach ($embeddings as $embedding) {
            $tenantId = $embedding['tenant_id'];
            $documentId = $embedding['knowledge_document_id'];
            $chunkText = addslashes($embedding['chunk_text']); // Escape single quotes
            $chunkIndex = $embedding['chunk_index'];
            $vectorString = '[' . implode(',', $embedding['embedding']) . ']';

            $values[] = "({$tenantId}, {$documentId}, '{$chunkText}', {$chunkIndex}, '{$vectorString}'::vector, NOW(), NOW())";
        }

        $valuesString = implode(', ', $values);

        $sql = "
            INSERT INTO knowledge_embeddings
            (tenant_id, knowledge_document_id, chunk_text, chunk_index, embedding, created_at, updated_at)
            VALUES {$valuesString}
        ";

        try {
            DB::statement($sql);
            return true;
        } catch (\Exception $e) {
            // Log error and return false
            \Illuminate\Support\Facades\Log::error('Batch embedding insert failed', [
                'error' => $e->getMessage(),
                'embedding_count' => count($embeddings)
            ]);
            return false;
        }
    }

    /**
     * Get embedding as formatted vector string
     *
     * @return string
     */
    public function getVectorString(): string
    {
        if (is_array($this->embedding)) {
            return '[' . implode(',', $this->embedding) . ']';
        }

        return $this->embedding;
    }

    /**
     * Calculate similarity with another embedding
     *
     * @param array $otherEmbedding
     * @return float
     */
    public function calculateSimilarity(array $otherEmbedding): float
    {
        if (!is_array($this->embedding)) {
            return 0.0;
        }

        $dotProduct = 0;
        $magnitudeA = 0;
        $magnitudeB = 0;

        foreach ($this->embedding as $i => $valueA) {
            if (!isset($otherEmbedding[$i])) {
                continue;
            }

            $valueB = $otherEmbedding[$i];
            $dotProduct += $valueA * $valueB;
            $magnitudeA += $valueA * $valueA;
            $magnitudeB += $valueB * $valueB;
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }
}
