<?php

namespace App\Services;

use App\Models\Tenant;
use Carbon\Carbon;

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
     * @return array ['eligible' => bool, 'reason' => string]
     */
    public function checkMessageEligibility(Tenant $tenant, string $message): array
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

        return [
            'eligible' => true,
            'reason' => 'Message eligible for auto-reply'
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
}
