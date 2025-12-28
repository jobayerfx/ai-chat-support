<?php

namespace App\Jobs;

use App\Models\KnowledgeDocument;
use App\Services\TextChunker;
use App\Services\EmbeddingService;
use App\Services\EmbeddingRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessKnowledgeDocument implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 120, 300]; // Retry delays: 30s, 2min, 5min
    public $timeout = 600; // 10 minutes timeout

    private int $documentId;

    /**
     * Create a new job instance.
     *
     * @param int $documentId
     */
    public function __construct(int $documentId)
    {
        $this->documentId = $documentId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(
        TextChunker $textChunker,
        EmbeddingService $embeddingService,
        EmbeddingRepository $embeddingRepository
    ) {
        DB::beginTransaction();

        try {
            // Find the document with locking to prevent concurrent processing
            $document = KnowledgeDocument::lockForUpdate()->find($this->documentId);

            if (!$document) {
                Log::warning('Knowledge document not found in job', [
                    'document_id' => $this->documentId
                ]);
                DB::rollBack();
                return;
            }

            // Check if document is already processed (idempotency)
            if ($this->isDocumentAlreadyProcessed($document)) {
                Log::info('Document already processed, skipping', [
                    'document_id' => $document->id
                ]);
                DB::rollBack();
                return;
            }

            Log::info('Starting knowledge document processing job', [
                'document_id' => $document->id,
                'title' => $document->title,
                'tenant_id' => $document->tenant_id,
                'content_length' => mb_strlen($document->content),
                'attempt' => $this->attempts()
            ]);

            // Step 1: Chunk the content
            $chunks = $this->chunkDocumentContent($textChunker, $document);

            if (empty($chunks)) {
                throw new \Exception('No chunks generated from document content');
            }

            Log::info('Document chunked successfully', [
                'document_id' => $document->id,
                'chunk_count' => count($chunks)
            ]);

            // Step 2: Generate embeddings
            $embeddings = $this->generateEmbeddings($embeddingService, $chunks, $document);

            if (empty($embeddings)) {
                throw new \Exception('No embeddings generated');
            }

            Log::info('Embeddings generated successfully', [
                'document_id' => $document->id,
                'embedding_count' => count($embeddings)
            ]);

            // Step 3: Store vectors
            $storeResult = $this->storeEmbeddings($embeddingRepository, $document, $chunks, $embeddings);

            if (!$storeResult['success']) {
                throw new \Exception('Failed to store embeddings: ' . implode(', ', $storeResult['errors']));
            }

            Log::info('Embeddings stored successfully', [
                'document_id' => $document->id,
                'stored_count' => $storeResult['stored_count']
            ]);

            DB::commit();

            Log::info('Knowledge document processing completed successfully', [
                'document_id' => $document->id,
                'total_chunks' => count($chunks),
                'total_embeddings' => count($embeddings),
                'processing_time' => now()->diffInSeconds($this->job->createdAt ?? now())
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error processing knowledge document in job', [
                'document_id' => $this->documentId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'max_attempts' => $this->tries
            ]);

            // Re-throw for retry (if attempts remaining)
            throw $e;
        }
    }

    /**
     * Check if document is already processed (idempotency check)
     */
    private function isDocumentAlreadyProcessed(KnowledgeDocument $document): bool
    {
        // Check if document has embeddings (simple check)
        return $document->knowledgeEmbeddings()->exists();
    }

    /**
     * Step 1: Chunk the document content
     */
    private function chunkDocumentContent(TextChunker $textChunker, KnowledgeDocument $document): array
    {
        try {
            $chunks = $textChunker->chunk($document->content);

            Log::debug('Document chunking completed', [
                'document_id' => $document->id,
                'total_chunks' => count($chunks),
                'avg_chunk_length' => count($chunks) > 0 ? array_sum(array_map('mb_strlen', $chunks)) / count($chunks) : 0
            ]);

            return $chunks;

        } catch (\Exception $e) {
            Log::error('Document chunking failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Step 2: Generate embeddings for chunks
     */
    private function generateEmbeddings(EmbeddingService $embeddingService, array $chunks, KnowledgeDocument $document): array
    {
        try {
            // Try batch generation first for better performance
            $embeddings = $embeddingService->generateEmbeddingsBatch($chunks);

            // If batch fails, fall back to individual generation
            if (empty($embeddings)) {
                Log::warning('Batch embedding generation failed, trying individual generation', [
                    'document_id' => $document->id,
                    'chunk_count' => count($chunks)
                ]);

                $embeddings = [];
                foreach ($chunks as $index => $chunk) {
                    $embedding = $embeddingService->generateEmbedding($chunk);

                    if ($embedding) {
                        $embeddings[] = $embedding;
                    } else {
                        Log::warning('Failed to generate embedding for chunk', [
                            'document_id' => $document->id,
                            'chunk_index' => $index,
                            'chunk_preview' => mb_substr($chunk, 0, 100)
                        ]);
                        // Continue with other chunks
                    }
                }
            }

            // Filter out null embeddings
            $embeddings = array_filter($embeddings, function ($embedding) {
                return $embedding !== null;
            });

            Log::debug('Embedding generation completed', [
                'document_id' => $document->id,
                'requested_chunks' => count($chunks),
                'generated_embeddings' => count($embeddings)
            ]);

            return array_values($embeddings); // Re-index array

        } catch (\Exception $e) {
            Log::error('Embedding generation failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Step 3: Store embeddings in database
     */
    private function storeEmbeddings(EmbeddingRepository $embeddingRepository, KnowledgeDocument $document, array $chunks, array $embeddings): array
    {
        try {
            // Ensure we only store embeddings for chunks that were successfully embedded
            $validChunks = array_slice($chunks, 0, count($embeddings));
            $validEmbeddings = $embeddings;

            $result = $embeddingRepository->storeDocumentEmbeddings(
                $document->id,
                $document->tenant_id,
                $validChunks,
                $validEmbeddings
            );

            Log::debug('Embedding storage completed', [
                'document_id' => $document->id,
                'stored_count' => $result['stored_count'],
                'failed_count' => $result['failed_count']
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Embedding storage failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Handle job failure after all retries exhausted
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessKnowledgeDocument job failed permanently', [
            'document_id' => $this->documentId,
            'error' => $exception->getMessage(),
            'attempts' => $this->tries
        ]);

        // Mark document as failed (you could add a status field to KnowledgeDocument)
        // $document = KnowledgeDocument::find($this->documentId);
        // if ($document) {
        //     $document->update(['processing_status' => 'failed']);
        // }

        // TODO: Send notification to tenant about processing failure
        // TODO: Implement alerting system
    }

    /**
     * The unique ID of the job (prevents duplicate processing)
     */
    public function uniqueId(): string
    {
        return "process_document_{$this->documentId}";
    }

    /**
     * Get the middleware for the job
     */
    public function middleware(): array
    {
        return [
            // Add any job-specific middleware here
        ];
    }

    /**
     * Determine the time at which the job should timeout
     */
    public function retryUntil(): \DateTime
    {
        // Retry for up to 1 hour
        return now()->addHours(1);
    }
}
