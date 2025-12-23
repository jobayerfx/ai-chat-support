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
        // Validate request structure
        $validator = Validator::make($request->all(), [
            'event' => 'required|string',
            'data' => 'required|array',
            'data.inbox' => 'required|array',
            'data.inbox.id' => 'required|string',
            'data.conversation' => 'required|array',
            'data.conversation.id' => 'required|integer',
            'data.message_type' => 'required|string',
            'data.content' => 'required|string',
        ]);

        if ($validator->fails()) {
            Log::warning('Invalid webhook payload structure', [
                'errors' => $validator->errors(),
                'payload' => $request->all()
            ]);
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // Verify webhook signature
        if (!$this->validateSignature($request)) {
            Log::warning('Invalid Chatwoot webhook signature');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->all();

        // Log webhook payload safely
        Log::info('Chatwoot webhook received', [
            'event' => $payload['event'],
            'inbox_id' => $payload['data']['inbox']['id'],
            'conversation_id' => $payload['data']['conversation']['id'],
            'message_type' => $payload['data']['message_type'],
        ]);

        // Only handle message.created events
        if ($payload['event'] !== 'message.created') {
            return response()->json(['status' => 'ignored']);
        }

        // Ignore agent/system messages (only process incoming user messages)
        if ($payload['data']['message_type'] !== 'incoming') {
            return response()->json(['status' => 'ignored']);
        }

        // Extract required data
        $inboxId = $payload['data']['inbox']['id'];
        $conversationId = $payload['data']['conversation']['id'];
        $message = $payload['data']['content'];

        // Resolve tenant using TenantResolverService
        $tenant = $this->tenantResolver->resolveFromInboxId($inboxId);

        if (!$tenant) {
            Log::error('Tenant resolution failed for webhook', [
                'inbox_id' => $inboxId,
                'conversation_id' => $conversationId
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
