<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\AIConversation;

class AIEligibilityService
{
    private const HUMAN_TAKEOVER_KEYWORDS = ['human', 'agent', 'representative', 'support'];
    private const MIN_MESSAGE_LENGTH = 5;

    /**
     * Check if a message is eligible for AI processing
     *
     * @param Tenant $tenant
     * @param AIConversation $conversation
     * @param string $message
     * @return array ['eligible' => bool, 'reason' => string]
     */
    public function checkEligibility(Tenant $tenant, AIConversation $conversation, string $message): array
    {
        // Check if tenant has AI enabled
        if (!$this->isTenantAIEnabled($tenant)) {
            return [
                'eligible' => false,
                'reason' => 'AI not enabled for tenant'
            ];
        }

        // Check conversation AI status
        if (!$this->isConversationAIActive($conversation)) {
            return [
                'eligible' => false,
                'reason' => 'AI not active for conversation'
            ];
        }

        // Detect human takeover keywords
        if ($this->containsHumanTakeoverKeywords($message)) {
            return [
                'eligible' => false,
                'reason' => 'Human takeover requested'
            ];
        }

        // Enforce minimum message length
        if (!$this->meetsMinimumLength($message)) {
            return [
                'eligible' => false,
                'reason' => 'Message too short'
            ];
        }

        return [
            'eligible' => true,
            'reason' => 'Eligible for AI processing'
        ];
    }

    /**
     * Check if tenant has AI enabled
     */
    private function isTenantAIEnabled(Tenant $tenant): bool
    {
        return $tenant->ai_enabled ?? false;
    }

    /**
     * Check if conversation has AI active
     */
    private function isConversationAIActive(AIConversation $conversation): bool
    {
        return $conversation->ai_active ?? true;
    }

    /**
     * Check if message contains human takeover keywords
     */
    private function containsHumanTakeoverKeywords(string $message): bool
    {
        $lowerMessage = strtolower($message);
        foreach (self::HUMAN_TAKEOVER_KEYWORDS as $keyword) {
            if (str_contains($lowerMessage, $keyword)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if message meets minimum length requirement
     */
    private function meetsMinimumLength(string $message): bool
    {
        return strlen(trim($message)) >= self::MIN_MESSAGE_LENGTH;
    }
}
