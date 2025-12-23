<?php

namespace App\Services;

use App\Models\AIUsageLog;
use Illuminate\Support\Facades\Log;

class AIUsageLoggerService
{
    /**
     * Log AI usage for billing and analytics
     *
     * @param int $tenantId
     * @param int $conversationId
     * @param int $tokensUsed
     * @param string $decision 'ai' or 'human'
     * @param float|null $cost
     * @return bool Success status
     */
    public function logUsage(int $tenantId, int $conversationId, int $tokensUsed, string $decision, ?float $cost = null): bool
    {
        try {
            // Calculate cost if not provided
            if ($cost === null) {
                $cost = $this->calculateCost($tokensUsed);
            }

            AIUsageLog::create([
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'tokens_used' => $tokensUsed,
                'cost' => $cost,
                'decision' => $decision, // Note: May need to add 'decision' column to migration
            ]);

            Log::info('AI usage logged', [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'tokens_used' => $tokensUsed,
                'decision' => $decision,
                'cost' => $cost,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to log AI usage', [
                'tenant_id' => $tenantId,
                'conversation_id' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get usage statistics for a tenant
     *
     * @param int $tenantId
     * @param string|null $startDate
     * @param string|null $endDate
     * @return array
     */
    public function getTenantUsage(int $tenantId, ?string $startDate = null, ?string $endDate = null): array
    {
        $query = AIUsageLog::where('tenant_id', $tenantId);

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        $logs = $query->get();

        return [
            'total_tokens' => $logs->sum('tokens_used'),
            'total_cost' => $logs->sum('cost'),
            'ai_responses' => $logs->where('decision', 'ai')->count(),
            'human_transfers' => $logs->where('decision', 'human')->count(),
            'conversations' => $logs->unique('conversation_id')->count(),
        ];
    }

    /**
     * Calculate cost based on token usage
     *
     * @param int $tokens
     * @return float
     */
    private function calculateCost(int $tokens): float
    {
        // TODO: Use actual LLM pricing
        // Example: $0.0001 per token
        return $tokens * 0.0001;
    }
}
