<?php

namespace App\Services;

use App\Contracts\EmbeddingClient;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeEmbedding;
use Illuminate\Support\Facades\Log;

class KnowledgeProcessingService
{
    public function __construct(
        private EmbeddingClient $embeddingClient
    ) {}

    /**
     * Chunk text into smaller pieces for embedding
     *
     * @param string $text
     * @param int $chunkSize
     * @param int $overlap
     * @return array Array of text chunks
     */
    public function chunkText(string $text, int $chunkSize = 1000, int $overlap = 200): array
    {
        $chunks = [];
        $textLength = strlen($text);

        if ($textLength <= $chunkSize) {
            return [$text];
        }

        $start = 0;
        while ($start < $textLength) {
            $end = min($start + $chunkSize, $textLength);

            // Try to end at a sentence or word boundary
            if ($end < $textLength) {
                // Look for sentence endings
                $sentenceEndings = ['. ', '! ', '? ', "\n\n"];
                foreach ($sentenceEndings as $ending) {
                    $pos = strrpos(substr($text, $start, $end - $start), $ending);
                    if ($pos !== false) {
                        $end = $start + $pos + strlen($ending);
                        break;
                    }
                }

                // If no sentence ending found, try word boundary
                if ($end == $start + $chunkSize) {
                    $spacePos = strrpos(substr($text, $start, $end - $start), ' ');
                    if ($spacePos !== false) {
                        $end = $start + $spacePos + 1;
                    }
                }
            }

            $chunk = trim(substr($text, $start, $end - $start));
            if (!empty($chunk)) {
                $chunks[] = $chunk;
            }

            // Move start position with overlap
            $start = max($start + 1, $end - $overlap);
        }

        return $chunks;
    }

    /**
     * Generate embeddings for text chunks
     *
     * @param array $chunks
     * @return array Array of embedding vectors
     */
    public function generateEmbeddings(array $chunks): array
    {
        $embeddings = [];

        foreach ($chunks as $chunk) {
            try {
                $embedding = $this->embeddingClient->generateEmbedding($chunk);
                $embeddings[] = $embedding;

                Log::debug('Generated embedding for chunk', [
                    'chunk_length' => strlen($chunk),
                    'embedding_dimension' => count($embedding)
                ]);

            } catch (\Exception $e) {
                Log::error('Failed to generate embedding for chunk', [
                    'chunk_preview' => substr($chunk, 0, 100),
                    'error' => $e->getMessage()
                ]);

                // Continue with other chunks
                continue;
            }
        }

        return $embeddings;
    }

    /**
     * Process a knowledge document: chunk text and generate embeddings
     *
     * @param KnowledgeDocument $document
     * @return bool Success status
     */
    public function processDocument(KnowledgeDocument $document): bool
    {
        try {
            Log::info('Starting document processing', [
                'document_id' => $document->id,
                'title' => $document->title,
                'content_length' => strlen($document->content)
            ]);

            // Chunk the text
            $chunks = $this->chunkText($document->content);

            Log::info('Text chunked', [
                'document_id' => $document->id,
                'total_chunks' => count($chunks)
            ]);

            // Generate embeddings
            $embeddings = $this->generateEmbeddings($chunks);

            // Store embeddings in database
            $this->storeEmbeddings($document, $chunks, $embeddings);

            Log::info('Document processing completed', [
                'document_id' => $document->id,
                'chunks_stored' => count($chunks),
                'embeddings_stored' => count($embeddings)
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Document processing failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Store embeddings in the database
     *
     * @param KnowledgeDocument $document
     * @param array $chunks
     * @param array $embeddings
     * @return void
     */
    private function storeEmbeddings(KnowledgeDocument $document, array $chunks, array $embeddings): void
    {
        $embeddingsData = [];

        foreach ($chunks as $index => $chunk) {
            if (isset($embeddings[$index])) {
                $embeddingsData[] = [
                    'knowledge_document_id' => $document->id,
                    'tenant_id' => $document->tenant_id,
                    'chunk_text' => $chunk,
                    'chunk_index' => $index,
                    'embedding' => $embeddings[$index], // Store as array (will be JSON encoded by Eloquent)
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Bulk insert embeddings
        KnowledgeEmbedding::insert($embeddingsData);
    }

    /**
     * Extract text from uploaded file
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return string Extracted text content
     * @throws \Exception If file processing fails
     */
    public function extractTextFromFile($file): string
    {
        $mimeType = $file->getMimeType();
        $path = $file->getRealPath();

        switch ($mimeType) {
            case 'text/plain':
                return file_get_contents($path);

            case 'application/pdf':
                return $this->extractFromPdf($path);

            case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document': // .docx
                return $this->extractFromDocx($path);

            default:
                throw new \Exception("Unsupported file type: {$mimeType}");
        }
    }

    /**
     * Extract text from PDF file
     *
     * @param string $path
     * @return string
     */
    private function extractFromPdf(string $path): string
    {
        // TODO: Implement PDF text extraction
        // This would require a PDF parsing library like smalot/pdfparser
        throw new \Exception('PDF extraction not implemented yet');
    }

    /**
     * Extract text from DOCX file
     *
     * @param string $path
     * @return string
     */
    private function extractFromDocx(string $path): string
    {
        // TODO: Implement DOCX text extraction
        // This would require a library like phpoffice/phpword
        throw new \Exception('DOCX extraction not implemented yet');
    }
}
