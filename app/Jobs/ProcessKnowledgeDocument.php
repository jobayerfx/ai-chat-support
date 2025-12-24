<?php

namespace App\Jobs;

use App\Models\KnowledgeDocument;
use App\Services\KnowledgeProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessKnowledgeDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60]; // Retry delays in seconds

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
    public function handle(KnowledgeProcessingService $processingService)
    {
        try {
            // Find the document
            $document = KnowledgeDocument::find($this->documentId);

            if (!$document) {
                Log::error('Knowledge document not found in job', [
                    'document_id' => $this->documentId
                ]);
                return;
            }

            Log::info('Starting knowledge document processing job', [
                'document_id' => $document->id,
                'title' => $document->title,
                'tenant_id' => $document->tenant_id
            ]);

            // Process the document (chunk and generate embeddings)
            $success = $processingService->processDocument($document);

            if ($success) {
                Log::info('Knowledge document processing completed successfully', [
                    'document_id' => $document->id
                ]);
            } else {
                Log::error('Knowledge document processing failed', [
                    'document_id' => $document->id
                ]);

                throw new \Exception('Document processing failed');
            }

        } catch (\Exception $e) {
            Log::error('Error processing knowledge document in job', [
                'document_id' => $this->documentId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            throw $e; // Re-throw for retry
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessKnowledgeDocument job failed permanently', [
            'document_id' => $this->documentId,
            'error' => $exception->getMessage()
        ]);

        // TODO: Send alert or notification to tenant
        // TODO: Mark document as failed in database
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return "process_document_{$this->documentId}";
    }
}
