<?php

namespace App\Services;

use App\Models\ChatwootInbox;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatwootConnectionService
{
    /**
     * Test Chatwoot connection and get account info
     *
     * @param string $baseUrl
     * @param string $apiKey
     * @return array|null Account data or null on failure
     */
    public function testConnection(string $baseUrl, string $apiKey): ?array
    {
        try {
            $response = Http::withHeaders([
                'api_access_token' => $apiKey,
                'Content-Type' => 'application/json',
            ])->get("{$baseUrl}/api/v1/accounts");

            if ($response->successful()) {
                $accounts = $response->json();
                if (!empty($accounts)) {
                    return $accounts[0]; // Return first account
                }
            }

            Log::error('Chatwoot connection test failed', [
                'base_url' => $baseUrl,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Exception testing Chatwoot connection', [
                'base_url' => $baseUrl,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Get inboxes for an account
     *
     * @param string $baseUrl
     * @param string $apiKey
     * @param int $accountId
     * @return array|null Inboxes data or null on failure
     */
    public function getInboxes(string $baseUrl, string $apiKey, int $accountId): ?array
    {
        try {
            $response = Http::withHeaders([
                'api_access_token' => $apiKey,
                'Content-Type' => 'application/json',
            ])->get("{$baseUrl}/api/v1/accounts/{$accountId}/inboxes");

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to get Chatwoot inboxes', [
                'base_url' => $baseUrl,
                'account_id' => $accountId,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Exception getting Chatwoot inboxes', [
                'base_url' => $baseUrl,
                'account_id' => $accountId,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Save Chatwoot connection for a tenant
     *
     * @param Tenant $tenant
     * @param array $connectionData
     * @return ChatwootInbox
     */
    public function saveConnection(Tenant $tenant, array $connectionData): ChatwootInbox
    {
        return ChatwootInbox::create([
            'tenant_id' => $tenant->id,
            'inbox_id' => $connectionData['inbox_id'],
            'base_url' => $connectionData['base_url'],
            'api_key' => $connectionData['api_key'],
            'account_id' => $connectionData['account_id'],
            'webhook_secret' => $connectionData['webhook_secret'] ?? null,
            'name' => $connectionData['name'],
            'is_connected' => true,
        ]);
    }

    /**
     * Register webhook for an inbox
     *
     * @param ChatwootInbox $inbox
     * @param string $webhookUrl
     * @return bool Success status
     */
    public function registerWebhook(ChatwootInbox $inbox, string $webhookUrl): bool
    {
        try {
            $webhookSecret = $inbox->webhook_secret ?: Str::random(32);

            $response = Http::withHeaders([
                'api_access_token' => $inbox->api_key,
                'Content-Type' => 'application/json',
            ])->post("{$inbox->base_url}/api/v1/accounts/{$inbox->account_id}/webhooks", [
                'url' => $webhookUrl,
                'webhook_type' => 'account',
                'subscriptions' => ['message.created'],
            ]);

            if ($response->successful()) {
                // Update webhook secret in database
                $inbox->update(['webhook_secret' => $webhookSecret]);

                Log::info('Chatwoot webhook registered', [
                    'inbox_id' => $inbox->inbox_id,
                    'webhook_url' => $webhookUrl
                ]);

                return true;
            }

            Log::error('Failed to register Chatwoot webhook', [
                'inbox_id' => $inbox->inbox_id,
                'webhook_url' => $webhookUrl,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Exception registering Chatwoot webhook', [
                'inbox_id' => $inbox->inbox_id,
                'webhook_url' => $webhookUrl,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Get existing connections for a tenant
     *
     * @param Tenant $tenant
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getConnections(Tenant $tenant)
    {
        return $tenant->chatwootInboxes;
    }

    /**
     * Disconnect an inbox
     *
     * @param ChatwootInbox $inbox
     * @return bool Success status
     */
    public function disconnect(ChatwootInbox $inbox): bool
    {
        try {
            // TODO: Unregister webhook from Chatwoot if needed

            $inbox->update(['is_connected' => false]);

            Log::info('Chatwoot inbox disconnected', [
                'inbox_id' => $inbox->inbox_id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Exception disconnecting Chatwoot inbox', [
                'inbox_id' => $inbox->inbox_id,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }
}
