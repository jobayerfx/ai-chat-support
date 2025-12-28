<?php

namespace App\Services;

use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AIEligibilityService
{
    private const MIN_WORDS = 3;

    /**
     * Check if a tenant is eligible for AI processing
     *
     * @param Tenant $tenant
     * @return array ['eligible' => bool, 'reason' => string]
     */
    public function checkTenantEligibility(Tenant $tenant): array
    {
        // Check if tenant exists
        if (!$tenant) {
            return [
                'eligible' => false,
                'reason' => 'Tenant not found'
            ];
        }

        // Check if Chatwoot is connected
        if (!$this->isChatwootConnected($tenant)) {
            return [
                'eligible' => false,
                'reason' => 'Chatwoot not connected'
            ];
        }

        // Check if AI is enabled
        if (!$this->isAIEnabled($tenant)) {
            return [
                'eligible' => false,
                'reason' => 'AI not enabled'
            ];
        }

        return [
            'eligible' => true,
            'reason' => 'Tenant eligible for AI processing'
        ];
    }

    /**
     * Check if a message is eligible for auto-reply
     *
     * @param Tenant $tenant
     * @param string $message
     * @param array $conversationContext Additional context about the conversation
     * @return array ['eligible' => bool, 'reason' => string]
     */
    public function checkMessageEligibility(Tenant $tenant, string $message, array $conversationContext = []): array
    {
        // Check business hours
        if (!$this->isWithinBusinessHours($tenant)) {
            return [
                'eligible' => false,
                'reason' => 'Outside business hours'
            ];
        }

        // Check message length
        if (!$this->meetsMinimumWordCount($message)) {
            return [
                'eligible' => false,
                'reason' => 'Message too short (less than 3 words)'
            ];
        }

        // Check for abusive content
        if ($this->containsAbusiveContent($message)) {
            return [
                'eligible' => false,
                'reason' => 'Message contains abusive content'
            ];
        }

        // Check conversation rate limit
        $conversationId = $conversationContext['conversation_id'] ?? null;
        if ($conversationId && !$this->checkConversationRateLimit($conversationId)) {
            return [
                'eligible' => false,
                'reason' => 'Conversation rate limit exceeded'
            ];
        }

        // Check if conversation already handled by agent
        if ($this->isConversationHandledByAgent($conversationContext)) {
            return [
                'eligible' => false,
                'reason' => 'Conversation already handled by agent'
            ];
        }

        return [
            'eligible' => true,
            'reason' => 'Message eligible for auto-reply'
        ];
    }

    /**
     * Check knowledge confidence for auto-reply decision
     *
     * @param array $knowledgeContext Retrieved knowledge snippets
     * @param string $message User message
     * @return array ['confident' => bool, 'confidence_score' => float, 'reason' => string]
     */
    public function checkKnowledgeConfidence(array $knowledgeContext, string $message): array
    {
        if (empty($knowledgeContext)) {
            return [
                'confident' => false,
                'confidence_score' => 0.0,
                'reason' => 'No knowledge available'
            ];
        }

        // Calculate confidence based on knowledge relevance and coverage
        $confidenceScore = $this->calculateKnowledgeConfidence($knowledgeContext, $message);

        $minConfidenceThreshold = 0.3; // Configurable threshold

        if ($confidenceScore < $minConfidenceThreshold) {
            return [
                'confident' => false,
                'confidence_score' => $confidenceScore,
                'reason' => 'Knowledge confidence too low'
            ];
        }

        return [
            'confident' => true,
            'confidence_score' => $confidenceScore,
            'reason' => 'Knowledge confidence acceptable'
        ];
    }

    /**
     * Check if tenant has Chatwoot connected
     */
    private function isChatwootConnected(Tenant $tenant): bool
    {
        return $tenant->settings?->chatwoot_connected ?? false;
    }

    /**
     * Check if tenant has AI enabled
     */
    private function isAIEnabled(Tenant $tenant): bool
    {
        return $tenant->ai_enabled ?? false;
    }

    /**
     * Check if current time is within business hours
     */
    private function isWithinBusinessHours(Tenant $tenant): bool
    {
        $settings = $tenant->settings;

        if (!$settings) {
            // Default to always available if no settings
            return true;
        }

        // Get business hours configuration (assuming these fields exist in settings)
        $businessHoursEnabled = $settings->business_hours_enabled ?? true;
        $timezone = $settings->timezone ?? 'UTC';
        $businessStart = $settings->business_start_time ?? '09:00';
        $businessEnd = $settings->business_end_time ?? '17:00';
        $businessDays = $settings->business_days ?? [1, 2, 3, 4, 5]; // Monday to Friday

        if (!$businessHoursEnabled) {
            return true;
        }

        $now = Carbon::now($timezone);
        $currentDay = $now->dayOfWeek; // 0 = Sunday, 1 = Monday, etc.
        $currentTime = $now->format('H:i');

        // Check if current day is a business day
        if (!in_array($currentDay, $businessDays)) {
            return false;
        }

        // Check if current time is within business hours
        return $currentTime >= $businessStart && $currentTime <= $businessEnd;
    }

    /**
     * Check if message meets minimum word count
     */
    private function meetsMinimumWordCount(string $message): bool
    {
        $words = str_word_count(trim($message));
        return $words >= self::MIN_WORDS;
    }

    /**
     * Check if message contains abusive content
     */
    private function containsAbusiveContent(string $message): bool
    {
        $abusiveWords = [
            'fuck', 'shit', 'damn', 'asshole', 'bastard', 'bitch', 'crap',
            'stupid', 'idiot', 'moron', 'dumb', 'retard', 'loser',
            'hate', 'kill', 'die', 'death', 'murder', 'rape', 'sex',
            'porn', 'nude', 'naked', 'drug', 'cocaine', 'heroin', 'weed'
        ];

        $lowerMessage = strtolower($message);

        foreach ($abusiveWords as $word) {
            if (str_contains($lowerMessage, $word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check conversation rate limit (max 3 AI replies per conversation per hour)
     */
    private function checkConversationRateLimit(int $conversationId): bool
    {
        $cacheKey = "conversation_replies_{$conversationId}";
        $currentReplies = Cache::get($cacheKey, 0);
        $maxRepliesPerHour = 3;

        return $currentReplies < $maxRepliesPerHour;
    }

    /**
     * Check if conversation is already handled by an agent
     */
    private function isConversationHandledByAgent(array $conversationContext): bool
    {
        // Check if there are recent agent messages in the conversation
        $recentAgentMessages = $conversationContext['recent_agent_messages'] ?? 0;
        $lastAgentMessageTime = $conversationContext['last_agent_message_time'] ?? null;

        // If agent has replied in the last 30 minutes, don't auto-reply
        if ($lastAgentMessageTime && Carbon::parse($lastAgentMessageTime)->diffInMinutes(Carbon::now()) < 30) {
            return true;
        }

        // If there are multiple agent messages, consider it handled
        if ($recentAgentMessages > 1) {
            return true;
        }

        return false;
    }

    /**
     * Calculate knowledge confidence score
     */
    private function calculateKnowledgeConfidence(array $knowledgeContext, string $message): float
    {
        if (empty($knowledgeContext)) {
            return 0.0;
        }

        $totalScore = 0.0;
        $messageWords = str_word_count(strtolower($message));

        foreach ($knowledgeContext as $snippet) {
            $snippetWords = str_word_count(strtolower($snippet));

            // Calculate word overlap
            $commonWords = array_intersect($messageWords, $snippetWords);
            $overlapScore = count($commonWords) / max(count($messageWords), 1);

            // Calculate length relevance (prefer snippets that aren't too short or long)
            $lengthScore = 1.0;
            $snippetLength = strlen($snippet);
            if ($snippetLength < 50) {
                $lengthScore = 0.5; // Too short
            } elseif ($snippetLength > 1000) {
                $lengthScore = 0.7; // Long but still relevant
            }

            $snippetScore = $overlapScore * $lengthScore;
            $totalScore += $snippetScore;
        }

        // Average score across all snippets
        $averageScore = $totalScore / count($knowledgeContext);

        // Boost score if we have multiple relevant snippets
        $snippetCountBonus = min(count($knowledgeContext) / 3, 1.0);

        return min($averageScore * (1 + $snippetCountBonus * 0.2), 1.0);
    }
}
