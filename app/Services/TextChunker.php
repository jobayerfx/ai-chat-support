<?php

namespace App\Services;

class TextChunker
{
    /**
     * Approximate characters per token (rough estimate for most languages)
     * This is a simplification - actual tokenization varies by model
     */
    private const CHARS_PER_TOKEN = 4;

    /**
     * Default chunk size in tokens
     */
    private const DEFAULT_CHUNK_SIZE = 500;

    /**
     * Default overlap size in tokens
     */
    private const DEFAULT_OVERLAP_SIZE = 50;

    /**
     * Chunk text into smaller pieces suitable for AI embeddings
     *
     * @param string $text The input text to chunk
     * @param int|null $chunkSizeTokens Desired chunk size in tokens (default: 500)
     * @param int|null $overlapTokens Overlap between chunks in tokens (default: 50)
     * @return array Array of text chunks
     */
    public function chunk(string $text, ?int $chunkSizeTokens = null, ?int $overlapTokens = null): array
    {
        // Use defaults if not specified
        $chunkSizeTokens = $chunkSizeTokens ?? self::DEFAULT_CHUNK_SIZE;
        $overlapTokens = $overlapTokens ?? self::DEFAULT_OVERLAP_SIZE;

        // Convert token counts to character counts
        $chunkSizeChars = $chunkSizeTokens * self::CHARS_PER_TOKEN;
        $overlapChars = $overlapTokens * self::CHARS_PER_TOKEN;

        // Clean and normalize the text
        $text = $this->normalizeText($text);

        // If text is shorter than chunk size, return as single chunk
        if (mb_strlen($text) <= $chunkSizeChars) {
            return [$text];
        }

        $chunks = [];
        $textLength = mb_strlen($text);
        $start = 0;

        while ($start < $textLength) {
            // Calculate end position for this chunk
            $end = min($start + $chunkSizeChars, $textLength);

            // If we're not at the end, try to find a good break point
            if ($end < $textLength) {
                $end = $this->findOptimalBreakPoint($text, $start, $end);
            }

            // Extract the chunk
            $chunk = mb_substr($text, $start, $end - $start);
            $chunks[] = trim($chunk);

            // Move start position for next chunk (with overlap)
            $start = $end - $overlapChars;

            // Prevent infinite loop
            if ($start >= $textLength || $start <= ($end - $overlapChars)) {
                break;
            }
        }

        return array_filter($chunks, function ($chunk) {
            return !empty(trim($chunk));
        });
    }

    /**
     * Normalize text for better chunking
     *
     * @param string $text
     * @return string
     */
    private function normalizeText(string $text): string
    {
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        // Trim whitespace from start and end
        $text = trim($text);

        return $text;
    }

    /**
     * Find an optimal break point within the chunk boundaries
     * Prefers breaking at sentence endings, then paragraphs, then words
     *
     * @param string $text
     * @param int $start
     * @param int $end
     * @return int
     */
    private function findOptimalBreakPoint(string $text, int $start, int $end): int
    {
        $chunk = mb_substr($text, $start, $end - $start);

        // Look for sentence endings (., !, ?) within the last 20% of the chunk
        $searchStart = max(0, mb_strlen($chunk) - (int)($end * 0.2));
        $sentenceEndings = ['. ', '! ', '? '];

        foreach ($sentenceEndings as $ending) {
            $pos = mb_strrpos($chunk, $ending, $searchStart);
            if ($pos !== false) {
                return $start + $pos + mb_strlen($ending);
            }
        }

        // Look for paragraph breaks (\n\n)
        $paragraphBreak = mb_strrpos($chunk, "\n\n", $searchStart);
        if ($paragraphBreak !== false) {
            return $start + $paragraphBreak + 2;
        }

        // Look for line breaks (\n)
        $lineBreak = mb_strrpos($chunk, "\n", $searchStart);
        if ($lineBreak !== false) {
            return $start + $lineBreak + 1;
        }

        // Look for word boundaries (spaces)
        $spacePos = mb_strrpos($chunk, ' ', $searchStart);
        if ($spacePos !== false) {
            return $start + $spacePos + 1;
        }

        // If no good break point found, return the original end
        return $end;
    }

    /**
     * Estimate token count for a text string
     *
     * @param string $text
     * @return int
     */
    public function estimateTokenCount(string $text): int
    {
        return (int) ceil(mb_strlen($text) / self::CHARS_PER_TOKEN);
    }

    /**
     * Get chunk statistics
     *
     * @param string $text
     * @param int|null $chunkSizeTokens
     * @param int|null $overlapTokens
     * @return array
     */
    public function getChunkStats(string $text, ?int $chunkSizeTokens = null, ?int $overlapTokens = null): array
    {
        $chunks = $this->chunk($text, $chunkSizeTokens, $overlapTokens);

        $chunkSizeTokens = $chunkSizeTokens ?? self::DEFAULT_CHUNK_SIZE;
        $overlapTokens = $overlapTokens ?? self::DEFAULT_OVERLAP_SIZE;

        return [
            'total_text_length' => mb_strlen($text),
            'estimated_total_tokens' => $this->estimateTokenCount($text),
            'chunk_count' => count($chunks),
            'chunk_size_tokens' => $chunkSizeTokens,
            'overlap_tokens' => $overlapTokens,
            'average_chunk_length' => count($chunks) > 0 ? array_sum(array_map('mb_strlen', $chunks)) / count($chunks) : 0,
            'chunks' => array_map(function ($chunk) {
                return [
                    'length' => mb_strlen($chunk),
                    'estimated_tokens' => $this->estimateTokenCount($chunk),
                    'preview' => mb_substr($chunk, 0, 100) . (mb_strlen($chunk) > 100 ? '...' : ''),
                ];
            }, $chunks),
        ];
    }

    /**
     * Validate chunking parameters
     *
     * @param int $chunkSizeTokens
     * @param int $overlapTokens
     * @return array
     */
    public function validateParameters(int $chunkSizeTokens, int $overlapTokens): array
    {
        $errors = [];

        if ($chunkSizeTokens <= 0) {
            $errors[] = 'Chunk size must be greater than 0';
        }

        if ($overlapTokens < 0) {
            $errors[] = 'Overlap size cannot be negative';
        }

        if ($overlapTokens >= $chunkSizeTokens) {
            $errors[] = 'Overlap size must be less than chunk size';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
