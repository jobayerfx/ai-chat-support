<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Tenant;
use App\Services\AIEligibilityService;
use App\Services\KnowledgeRetrievalService;
use App\Services\PromptBuilderService;
use App\Services\AIChatCompletionService;
use App\Services\ChatwootMessageSenderService;
use App\Services\AIUsageLoggerService;

class ProcessChatwootMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $payload;
    protected $tenant;

    /**
     * Create a new job instance.
     */
    public function __construct(array $payload, Tenant $tenant)
    {
        $this->payload = $payload;
        $this->tenant = $tenant;
    }

    /**
     * Execute the job.
     */
    public function handle(
        AIEligibilityService $eligibilityService,
        KnowledgeRetrievalService $knowledgeService,
        PromptBuilderService $promptBuilder,
        AIChatCompletionService $chatCompletion,
        ChatwootMessageSenderService $messageSender,
        AIUsageLoggerService $usageLogger
    ): void {
        $conversationId = $this->payload['data']['conversation']['id'] ?? null;
        $messageId = $this->payload['data']['id'] ?? null;
        $messageContent = $this->payload['data']['content'] ?? '';
        $inboxId = $this->payload['data']['inbox']['id'] ?? null;

        // Create idempotency key to prevent double processing
        $idempotencyKey = "chatwoot_message_{$conversationId}_{$messageId}";
        $cacheKey = "processed_{$idempotencyKey}";

        if (Cache::get($cacheKey)) {
            Log::info('Message already processed, skipping', [
                'conversation_id' => $conversationId,
                'message_id' => $messageId
            ]);
            return;
        }

        Log::info('Processing Chatwoot message for tenant', [
            'tenant_id' => $this->tenant->id,
            'tenant_name' => $this->tenant->name,
            'conversation_id' => $conversationId,
            'message_id' => $messageId,
            'inbox_id' => $inboxId,
            'message_content' => substr($messageContent, 0, 100),
        ]);

        try {
            // Step 1: Resolve tenant (already done in webhook controller)
            if (!$this->tenant) {
                Log::error('Tenant not resolved for message processing', [
                    'conversation_id' => $conversationId,
                    'message_id' => $messageId
                ]);
                return;
            }

            // Step 2: Gatekeeper check
            $tenantEligibility = $eligibilityService->checkTenantEligibility($this->tenant);
            if (!$tenantEligibility['eligible']) {
                Log::info('Tenant not eligible for AI processing', [
                    'tenant_id' => $this->tenant->id,
                    'reason' => $tenantEligibility['reason']
                ]);
                $usageLogger->logUsage($this->tenant->id, $conversationId, 0, 'ineligible');
                return;
            }

            $messageEligibility = $eligibilityService->checkMessageEligibility($this->tenant, $messageContent);
            if (!$messageEligibility['eligible']) {
                Log::info('Message not eligible for AI processing', [
                    'tenant_id' => $this->tenant->id,
                    'conversation_id' => $conversationId,
                    'reason' => $messageEligibility['reason']
                ]);
                $usageLogger->logUsage($this->tenant->id, $conversationId, 0, 'ineligible');
                return;
            }

            // Step 3: Retrieve knowledge
            $knowledgeContext = $knowledgeService->retrieveRelevantKnowledge($this->tenant, $messageContent);

            if (empty($knowledgeContext)) {
                Log::info('No knowledge found for message', [
                    'tenant_id' => $this->tenant->id,
                    'conversation_id' => $conversationId
                ]);
                $usageLogger->logUsage($this->tenant->id, $conversationId, 0, 'no_knowledge');
                return;
            }

            // Step 4: Build prompt
            $prompt = $promptBuilder->buildChatPrompt($messageContent, $knowledgeContext);

            // Step 5: Generate AI reply
            $aiResponse = $chatCompletion->generateCompletion($prompt);

            if (!$aiResponse) {
                Log::error('Failed to generate AI response', [
                    'tenant_id' => $this->tenant->id,
                    'conversation_id' => $conversationId
                ]);
                $usageLogger->logUsage($this->tenant->id, $conversationId, 0, 'ai_failed');
                return;
            }

            // Step 6: Send to Chatwoot
            $inbox = $this->tenant->chatwootInboxes()->where('inbox_id', $inboxId)->first();

            if (!$inbox) {
                Log::error('Chatwoot inbox not found for tenant', [
                    'tenant_id' => $this->tenant->id,
                    'inbox_id' => $inboxId
                ]);
                return;
            }

            $messageSent = $messageSender->sendPublicMessage($inbox, $conversationId, $aiResponse);

            if (!$messageSent) {
                Log::error('Failed to send AI response to Chatwoot', [
                    'tenant_id' => $this->tenant->id,
                    'conversation_id' => $conversationId
                ]);
                return;
            }

            // Step 7: Log AI usage
            // Estimate tokens used (rough calculation: 4 chars per token)
            $estimatedTokens = (strlen($prompt) + strlen($aiResponse)) / 4;
            $usageLogger->logUsage($this->tenant->id, $conversationId, (int)$estimatedTokens, 'ai');

            // Mark as processed to prevent double replies
            Cache::put($cacheKey, true, 3600); // Cache for 1 hour

            Log::info('AI auto-reply completed successfully', [
                'tenant_id' => $this->tenant->id,
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
                'response_length' => strlen($aiResponse)
            ]);

        } catch (\Exception $e) {
            Log::error('Exception processing Chatwoot message', [
                'tenant_id' => $this->tenant->id,
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Don't rethrow - job should not retry on application errors
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessChatwootMessage job failed', [
            'tenant_id' => $this->tenant->id ?? null,
            'conversation_id' => $this->payload['data']['conversation']['id'] ?? null,
            'message_id' => $this->payload['data']['id'] ?? null,
            'error' => $exception->getMessage()
        ]);
    }
}
