<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Services\TenantResolverService;
use App\Jobs\ProcessChatwootMessage;

class ChatwootWebhookController extends Controller
{
    private TenantResolverService $tenantResolver;

    public function __construct(TenantResolverService $tenantResolver)
    {
        $this->tenantResolver = $tenantResolver;
    }

    public function handle(Request $request)
    {
        // Log raw payload for debugging
        $payload = $request->all();
        Log::info('Raw Chatwoot webhook payload', ['payload' => $payload]);

        // Validate request structure
        $validator = Validator::make($payload, [
            'event' => 'required|string',
            'data' => 'required|array',
            'data.inbox' => 'required|array',
            'data.inbox.id' => 'required|string',
            'data.conversation' => 'required|array',
            'data.conversation.id' => 'required|integer',
            'data.sender' => 'required|array',
            'data.sender.type' => 'required|string',
            'data.message_type' => 'required|string',
            'data.content' => 'required|string',
        ]);

        if ($validator->fails()) {
            Log::warning('Invalid webhook payload structure', [
                'errors' => $validator->errors(),
                'payload' => $payload
            ]);
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // Verify webhook signature (if provided)
        if (!$this->validateSignature($request)) {
            Log::warning('Invalid Chatwoot webhook signature');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Only handle message.created events
        if ($payload['event'] !== 'message.created') {
            return response()->json(['status' => 'ignored']);
        }

        // Extract required data
        $inboxId = $payload['data']['inbox']['id'];
        $conversationId = $payload['data']['conversation']['id'];
        $messageContent = $payload['data']['content'];
        $senderType = $payload['data']['sender']['type'];

        // Ignore bot messages and agent messages
        if (in_array($senderType, ['bot', 'agent'])) {
            return response()->json(['status' => 'ignored']);
        }

        // Resolve tenant using TenantResolverService
        $tenant = $this->tenantResolver->resolveFromInboxId($inboxId);

        if (!$tenant) {
            Log::error('Tenant resolution failed for webhook', [
                'inbox_id' => $inboxId,
                'conversation_id' => $conversationId,
                'sender_type' => $senderType
            ]);
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        // Dispatch processing to queued job
        ProcessChatwootMessage::dispatch($payload, $tenant);

        return response()->json(['status' => 'processed']);
    }

    /**
     * Validate Chatwoot webhook signature using HMAC-SHA256
     */
    private function validateSignature(Request $request): bool
    {
        $signature = $request->header('X-Chatwoot-Signature');
        $secret = config('chatwoot.webhook_secret');

        if (!$signature || !$secret) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
