<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatwootService
{
    private string $baseUrl;
    private string $apiKey;
    private int $accountId;

    public function __construct()
    {
        $this->baseUrl = config('chatwoot.base_url');
        $this->apiKey = config('chatwoot.api_key');
        $this->accountId = config('chatwoot.account_id');
    }

    /**
     * Send a message to a Chatwoot conversation
     *
     * @param int $conversationId
     * @param string $message
     * @return bool Success status
     */
    public function sendMessage(int $conversationId, string $message): bool
    {
        try {
            $response = Http::withHeaders([
                'api_access_token' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/api/v1/accounts/{$this->accountId}/conversations/{$conversationId}/messages", [
                'content' => $message,
                'message_type' => 'outgoing',
            ]);

            if ($response->successful()) {
                Log::info('Message sent to Chatwoot', ['conversation_id' => $conversationId]);
                return true;
            }

            Log::error('Failed to send message to Chatwoot', [
                'conversation_id' => $conversationId,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Exception sending message to Chatwoot', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Assign conversation to an agent
     *
     * @param int $conversationId
     * @param int $agentId
     * @return bool Success status
     */
    public function assignToAgent(int $conversationId, int $agentId): bool
    {
        try {
            $response = Http::withHeaders([
                'api_access_token' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/api/v1/accounts/{$this->accountId}/conversations/{$conversationId}/assignments", [
                'assignee_id' => $agentId,
            ]);

            if ($response->successful()) {
                Log::info('Conversation assigned to agent', [
                    'conversation_id' => $conversationId,
                    'agent_id' => $agentId
                ]);
                return true;
            }

            Log::error('Failed to assign conversation to agent', [
                'conversation_id' => $conversationId,
                'agent_id' => $agentId,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Exception assigning conversation to agent', [
                'conversation_id' => $conversationId,
                'agent_id' => $agentId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Disable AI for a conversation (mark as human takeover)
     *
     * @param int $conversationId
     * @return bool Success status
     */
    public function disableAI(int $conversationId): bool
    {
        try {
            // Add a label or custom attribute to mark AI as disabled
            $response = Http::withHeaders([
                'api_access_token' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->baseUrl}/api/v1/accounts/{$this->accountId}/conversations/{$conversationId}/labels", [
                'labels' => ['human_takeover'],
            ]);

            if ($response->successful()) {
                Log::info('AI disabled for conversation', ['conversation_id' => $conversationId]);
                return true;
            }

            Log::error('Failed to disable AI for conversation', [
                'conversation_id' => $conversationId,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Exception disabling AI for conversation', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get conversation details
     *
     * @param int $conversationId
     * @return array|null Conversation data or null on failure
     */
    public function getConversation(int $conversationId): ?array
    {
        try {
            $response = Http::withHeaders([
                'api_access_token' => $this->apiKey,
            ])->get("{$this->baseUrl}/api/v1/accounts/{$this->accountId}/conversations/{$conversationId}");

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to get conversation', [
                'conversation_id' => $conversationId,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Exception getting conversation', [
                'conversation_id' => $conversationId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }
}
