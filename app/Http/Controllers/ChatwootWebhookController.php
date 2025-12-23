<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\ChatwootInbox;
use App\Jobs\ProcessChatwootMessage;

class ChatwootWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // Validate webhook signature
        if (!$this->validateSignature($request)) {
            Log::warning('Invalid Chatwoot webhook signature');
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->all();

        // Log webhook payload safely (sanitize sensitive data)
        Log::info('Chatwoot webhook received', [
            'event' => $payload['event'] ?? 'unknown',
            'inbox_id' => $payload['data']['inbox']['id'] ?? null,
            'message_type' => $payload['data']['message_type'] ?? null,
        ]);

        // Only handle message.created events
        if (($payload['event'] ?? null) !== 'message.created') {
            return response()->json(['status' => 'ignored']);
        }

        // Ignore non-user messages (only process incoming messages)
        if (($payload['data']['message_type'] ?? null) !== 'incoming') {
            return response()->json(['status' => 'ignored']);
        }

        // Resolve tenant using inbox_id
        $inboxId = $payload['data']['inbox']['id'] ?? null;
        if (!$inboxId) {
            Log::warning('Missing inbox_id in webhook payload');
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $chatwootInbox = ChatwootInbox::where('inbox_id', $inboxId)->first();
        if (!$chatwootInbox) {
            Log::warning('Chatwoot inbox not found', ['inbox_id' => $inboxId]);
            return response()->json(['error' => 'Inbox not found'], 404);
        }

        $tenant = $chatwootInbox->tenant;

        // Dispatch processing to service class (job)
        ProcessChatwootMessage::dispatch($payload, $tenant);

        return response()->json(['status' => 'processed']);
    }

    /**
     * Validate Chatwoot webhook signature
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
