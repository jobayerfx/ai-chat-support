<?php

namespace App\Http\Controllers;

use App\Services\ChatwootConnectionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ChatwootConnectionController extends Controller
{
    public function __construct(
        private ChatwootConnectionService $connectionService
    ) {}

    /**
     * Test Chatwoot connection and get account info
     */
    public function testConnection(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'base_url' => 'required|url',
            'api_key' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $account = $this->connectionService->testConnection(
            $request->base_url,
            $request->api_key
        );

        if (!$account) {
            return response()->json([
                'message' => 'Connection failed. Please check your credentials.'
            ], 400);
        }

        return response()->json([
            'message' => 'Connection successful',
            'account' => $account
        ]);
    }

    /**
     * Get inboxes for the connected account
     */
    public function getInboxes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'base_url' => 'required|url',
            'api_key' => 'required|string',
            'account_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $inboxes = $this->connectionService->getInboxes(
            $request->base_url,
            $request->api_key,
            $request->account_id
        );

        if (!$inboxes) {
            return response()->json([
                'message' => 'Failed to fetch inboxes'
            ], 400);
        }

        return response()->json([
            'message' => 'Inboxes fetched successfully',
            'inboxes' => $inboxes
        ]);
    }

    /**
     * Save Chatwoot connection and register webhook
     */
    public function connect(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'base_url' => 'required|url',
            'api_key' => 'required|string',
            'account_id' => 'required|integer',
            'inbox_id' => 'required|string',
            'name' => 'required|string',
            'webhook_secret' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $tenant = $user->tenant;

        if (!$tenant) {
            return response()->json([
                'message' => 'User not associated with a tenant'
            ], 403);
        }

        try {
            // Check if inbox is already connected
            $existing = $tenant->chatwootInboxes()
                ->where('inbox_id', $request->inbox_id)
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'This inbox is already connected'
                ], 409);
            }

            // Save connection
            $connection = $this->connectionService->saveConnection($tenant, [
                'base_url' => $request->base_url,
                'api_key' => $request->api_key,
                'account_id' => $request->account_id,
                'inbox_id' => $request->inbox_id,
                'name' => $request->name,
                'webhook_secret' => $request->webhook_secret,
            ]);

            // Register webhook
            $webhookUrl = config('app.url') . '/api/webhooks/chatwoot';
            $webhookRegistered = $this->connectionService->registerWebhook($connection, $webhookUrl);

            if (!$webhookRegistered) {
                Log::warning('Webhook registration failed, but connection saved', [
                    'connection_id' => $connection->id,
                    'inbox_id' => $request->inbox_id
                ]);
            }

            return response()->json([
                'message' => 'Chatwoot connected successfully',
                'connection' => $connection,
                'webhook_registered' => $webhookRegistered
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to connect Chatwoot', [
                'tenant_id' => $tenant->id,
                'inbox_id' => $request->inbox_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to connect Chatwoot',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get existing connections for the tenant
     */
    public function getConnections()
    {
        $user = Auth::user();
        $tenant = $user->tenant;

        if (!$tenant) {
            return response()->json([
                'message' => 'User not associated with a tenant'
            ], 403);
        }

        $connections = $this->connectionService->getConnections($tenant);

        return response()->json([
            'message' => 'Connections retrieved successfully',
            'connections' => $connections
        ]);
    }

    /**
     * Disconnect an inbox
     */
    public function disconnect(Request $request, $connectionId)
    {
        $user = Auth::user();
        $tenant = $user->tenant;

        if (!$tenant) {
            return response()->json([
                'message' => 'User not associated with a tenant'
            ], 403);
        }

        $connection = $tenant->chatwootInboxes()->find($connectionId);

        if (!$connection) {
            return response()->json([
                'message' => 'Connection not found'
            ], 404);
        }

        $disconnected = $this->connectionService->disconnect($connection);

        if (!$disconnected) {
            return response()->json([
                'message' => 'Failed to disconnect'
            ], 500);
        }

        return response()->json([
            'message' => 'Disconnected successfully'
        ]);
    }
}
