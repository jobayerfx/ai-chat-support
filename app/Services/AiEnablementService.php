<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantSettings;
use Illuminate\Support\Facades\Log;

class AiEnablementService
{
    /**
     * Enable or disable AI for a tenant
     *
     * @param Tenant $tenant
     * @param bool $enabled
     * @return array
     */
    public function toggleAi(Tenant $tenant, bool $enabled): array
    {
        // Validate prerequisites for enabling AI
        if ($enabled) {
            $validationResult = $this->validateAiEnablementPrerequisites($tenant);

            if (!$validationResult['valid']) {
                return [
                    'success' => false,
                    'message' => $validationResult['message'],
                    'ai_enabled' => $tenant->ai_enabled,
                ];
            }
        }

        // Update AI enabled status
        $tenant->update([
            'ai_enabled' => $enabled,
        ]);

        // Handle onboarding progress
        if ($enabled) {
            $this->handleAiEnablement($tenant);
        } else {
            $this->handleAiDisablement($tenant);
        }

        Log::info($enabled ? 'AI enabled for tenant' : 'AI disabled for tenant', [
            'tenant_id' => $tenant->id,
            'ai_enabled' => $enabled,
        ]);

        return [
            'success' => true,
            'message' => $enabled ? 'AI enabled successfully' : 'AI disabled successfully',
            'ai_enabled' => $tenant->ai_enabled,
        ];
    }

    /**
     * Validate prerequisites for enabling AI
     *
     * @param Tenant $tenant
     * @return array
     */
    private function validateAiEnablementPrerequisites(Tenant $tenant): array
    {
        $settings = $tenant->settings;

        // Check if Chatwoot is connected
        if (!$settings || !$settings->chatwoot_connected) {
            return [
                'valid' => false,
                'message' => 'Cannot enable AI without connecting Chatwoot first. Please connect your Chatwoot account.',
            ];
        }

        // Additional validation can be added here
        // e.g., check if knowledge base is set up, billing status, etc.

        return [
            'valid' => true,
            'message' => null,
        ];
    }

    /**
     * Handle AI enablement logic
     *
     * @param Tenant $tenant
     * @return void
     */
    private function handleAiEnablement(Tenant $tenant): void
    {
        // Get or create tenant settings
        $settings = $tenant->settings ?? $tenant->settings()->create([]);

        // Mark AI config onboarding step as completed
        $settings->completeOnboardingStep('ai_config');

        // Initialize default AI settings if not already set
        if (!$settings->ai_configured) {
            $settings->update([
                'ai_configured' => true,
                // Other default AI settings can be set here
            ]);
        }

        // Additional enablement logic can be added here
        // e.g., send welcome email, create default AI configurations, etc.
    }

    /**
     * Handle AI disablement logic
     *
     * @param Tenant $tenant
     * @return void
     */
    private function handleAiDisablement(Tenant $tenant): void
    {
        // Additional disablement logic can be added here
        // e.g., clean up AI-related data, send notification, etc.

        Log::info('AI disabled for tenant', [
            'tenant_id' => $tenant->id,
        ]);
    }

    /**
     * Check if AI can be enabled for a tenant
     *
     * @param Tenant $tenant
     * @return array
     */
    public function canEnableAi(Tenant $tenant): array
    {
        return $this->validateAiEnablementPrerequisites($tenant);
    }

    /**
     * Get AI status for a tenant
     *
     * @param Tenant $tenant
     * @return array
     */
    public function getAiStatus(Tenant $tenant): array
    {
        $settings = $tenant->settings;

        return [
            'ai_enabled' => $tenant->ai_enabled,
            'chatwoot_connected' => $settings?->chatwoot_connected ?? false,
            'ai_configured' => $settings?->ai_configured ?? false,
            'can_enable' => $this->canEnableAi($tenant)['valid'],
        ];
    }
}
