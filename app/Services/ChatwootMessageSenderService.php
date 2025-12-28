<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\ChatwootInbox;

class ChatwootMessageSenderService
{
    private const TIMEOUT_SECONDS = 30;
    private const MAX_RETRIES = 3;

    /**
     * Send a message to a Chatwoot conversation
     *
     * @param ChatwootInbox $inbox The Chatwoot inbox with credentials
     * @param int $conversationId The conversation ID
     * @param string $message The message content
     * @param bool $isPrivate Whether this is a private note (default: false)
     * @return bool Success status
     */
    public function sendMessage(ChatwootInbox $inbox, int $conversationId, string $message, bool $isPrivate = false): bool
    {
        $apiKey = $inbox->api_key;
        $baseUrl = $inbox->base_url;
        $accountId = $inbox->account_id;

        if (!$apiKey || !$baseUrl || !$accountId) {
            Log::error('Chatwoot inbox credentials not configured', [
                'inbox_id' => $inbox->id,
                'conversation_id' => $conversationId
            ]);
            return false;
        }

        $payload = [
            'content' => trim($message),
            'message_type' => 'outgoing',
        ];

        // Add private flag if this is a private message
        if ($isPrivate) {
            $payload['private'] = true;
        }

        $attempt = 0;
        $lastException = null;

        while ($attempt < self::MAX_RETRIES) {
            try {
                $response = Http::withHeaders([
                    'api_access_token' => $apiKey,
                    'Content-Type' => 'application/json',
                ])->timeout(self::TIMEOUT_SECONDS)->post(
                    "{$baseUrl}/api/v1/accounts/{$accountId}/conversations/{$conversationId}/messages",
                    $payload
                );

                if ($response->successful()) {
                    $responseData = $response->json();

                    Log::info('Message sent to Chatwoot successfully', [
                        'inbox_id' => $inbox->id,
                        'conversation_id' => $conversationId,
                        'message_id' => $responseData['id'] ?? null,
                        'is_private' => $isPrivate,
                        'attempt' => $attempt + 1
                    ]);

                    return true;
                }

                // Handle specific error codes
                $statusCode = $response->status();
                $errorData = $response->json();

                if ($statusCode === 401) {
                    Log::error('Chatwoot authentication failed', [
                        'inbox_id' => $inbox->id,
                        'conversation_id' => $conversationId,
                        'status' => $statusCode,
                        'error' => $errorData
                    ]);
                    return false; // Don't retry auth failures

                } elseif ($statusCode === 404) {
                    Log::error('Chatwoot conversation not found', [
                        'inbox_id' => $inbox->id,
                        'conversation_id' => $conversationId,
                        'status' => $statusCode,
                        'error' => $errorData
                    ]);
                    return false; // Don't retry 404s

                } elseif ($statusCode === 422) {
                    Log::error('Chatwoot validation error', [
                        'inbox_id' => $inbox->id,
                        'conversation_id' => $conversationId,
                        'status' => $statusCode,
                        'error' => $errorData
                    ]);
                    return false; // Don't retry validation errors

                } elseif ($statusCode >= 500) {
                    Log::warning('Chatwoot server error, retrying', [
                        'inbox_id' => $inbox->id,
                        'conversation_id' => $conversationId,
                        'status' => $statusCode,
                        'error' => $errorData,
                        'attempt' => $attempt + 1
                    ]);

                    // Exponential backoff for server errors
                    sleep(2 ** $attempt);

                } elseif ($statusCode === 429) {
                    Log::warning('Chatwoot rate limit exceeded, retrying', [
                        'inbox_id' => $inbox->id,
                        'conversation_id' => $conversationId,
                        'status' => $statusCode,
                        'error' => $errorData,
                        'attempt' => $attempt + 1
                    ]);

                    // Exponential backoff for rate limits
                    sleep(2 ** $attempt);

                } else {
                    Log::error('Chatwoot API error', [
                        'inbox_id' => $inbox->id,
                        'conversation_id' => $conversationId,
                        'status' => $statusCode,
                        'error' => $errorData
                    ]);
                    return false;
                }

            } catch (\Exception $e) {
                $lastException = $e;
                Log::warning('Exception sending message to Chatwoot', [
                    'inbox_id' => $inbox->id,
                    'conversation_id' => $conversationId,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt + 1
                ]);

                // Exponential backoff for exceptions
                sleep(2 ** $attempt);
            }

            $attempt++;
        }

        Log::error('Failed to send message to Chatwoot after all retries', [
            'inbox_id' => $inbox->id,
            'conversation_id' => $conversationId,
            'attempts' => $attempt,
            'last_error' => $lastException?->getMessage()
        ]);

        return false;
    }

    /**
     * Send a private note to a conversation (visible only to agents)
     *
     * @param ChatwootInbox $inbox The Chatwoot inbox with credentials
     * @param int $conversationId The conversation ID
     * @param string $note The private note content
     * @return bool Success status
     */
    public function sendPrivateNote(ChatwootInbox $inbox, int $conversationId, string $note): bool
    {
        return $this->sendMessage($inbox, $conversationId, $note, true);
    }

    /**
     * Send a public message to a conversation (visible to customer)
     *
     * @param ChatwootInbox $inbox The Chatwoot inbox with credentials
     * @param int $conversationId The conversation ID
     * @param string $message The message content
     * @return bool Success status
     */
    public function sendPublicMessage(ChatwootInbox $inbox, int $conversationId, string $message): bool
    {
        return $this->sendMessage($inbox, $conversationId, $message, false);
    }

    /**
     * Test inbox connectivity by attempting to get account details
     *
     * @param ChatwootInbox $inbox The Chatwoot inbox to test
     * @return bool Connection successful
     */
    public function testConnection(ChatwootInbox $inbox): bool
    {
        $apiKey = $inbox->api_key;
        $baseUrl = $inbox->base_url;
        $accountId = $inbox->account_id;

        if (!$apiKey || !$baseUrl || !$accountId) {
            Log::error('Chatwoot inbox credentials not configured for connection test', [
                'inbox_id' => $inbox->id
            ]);
            return false;
        }

        try {
            $response = Http::withHeaders([
                'api_access_token' => $apiKey,
            ])->timeout(self::TIMEOUT_SECONDS)->get("{$baseUrl}/api/v1/accounts/{$accountId}");

            if ($response->successful()) {
                Log::info('Chatwoot connection test successful', [
                    'inbox_id' => $inbox->id,
                    'account_id' => $accountId
                ]);
                return true;
            }

            Log::error('Chatwoot connection test failed', [
                'inbox_id' => $inbox->id,
                'account_id' => $accountId,
                'status' => $response->status(),
                'error' => $response->json()
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Exception during Chatwoot connection test', [
                'inbox_id' => $inbox->id,
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }
}
