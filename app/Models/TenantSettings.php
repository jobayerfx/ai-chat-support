<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'onboarding_completed',
        'onboarding_steps',
        'chatwoot_base_url',
        'chatwoot_api_token',
        'chatwoot_connected',
        'chatwoot_inboxes',
        'knowledge_base_setup',
        'knowledge_documents_count',
        'last_knowledge_update',
        'ai_configured',
        'confidence_threshold',
        'human_override_enabled',
        'auto_escalate_threshold',
        'email_notifications',
        'ai_response_notifications',
        'company_logo_url',
        'primary_color',
        'secondary_color',
        'monthly_active_users',
        'total_conversations',
        'ai_responses_count',
    ];

    protected $casts = [
        'onboarding_completed' => 'boolean',
        'onboarding_steps' => 'array',
        'chatwoot_connected' => 'boolean',
        'chatwoot_inboxes' => 'array',
        'knowledge_base_setup' => 'boolean',
        'last_knowledge_update' => 'datetime',
        'ai_configured' => 'boolean',
        'confidence_threshold' => 'decimal:2',
        'human_override_enabled' => 'boolean',
        'auto_escalate_threshold' => 'decimal:2',
        'email_notifications' => 'boolean',
        'ai_response_notifications' => 'boolean',
    ];

    /**
     * Get the tenant that owns these settings.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Encrypt the Chatwoot API token when setting it.
     */
    public function setChatwootApiTokenAttribute($value)
    {
        if ($value) {
            $this->attributes['chatwoot_api_token'] = encrypt($value);
        } else {
            $this->attributes['chatwoot_api_token'] = null;
        }
    }

    /**
     * Decrypt the Chatwoot API token when getting it.
     */
    public function getChatwootApiTokenAttribute($value)
    {
        if ($value) {
            try {
                return decrypt($value);
            } catch (\Exception $e) {
                // If decryption fails, return null
                return null;
            }
        }
        return null;
    }

    /**
     * Check if onboarding is completed.
     */
    public function isOnboardingCompleted(): bool
    {
        return $this->onboarding_completed;
    }

    /**
     * Mark a specific onboarding step as completed.
     */
    public function completeOnboardingStep(string $step): void
    {
        $steps = $this->onboarding_steps ?? [];
        $steps[$step] = true;
        $this->onboarding_steps = $steps;

        // Check if all required steps are completed
        $requiredSteps = ['chatwoot_setup', 'knowledge_base', 'ai_config'];
        $allCompleted = collect($requiredSteps)->every(fn($step) => $steps[$step] ?? false);

        if ($allCompleted) {
            $this->onboarding_completed = true;
        }

        $this->save();
    }

    /**
     * Get onboarding progress as percentage.
     */
    public function getOnboardingProgress(): int
    {
        $steps = $this->onboarding_steps ?? [];
        $requiredSteps = ['chatwoot_setup', 'knowledge_base', 'ai_config'];
        $completedSteps = collect($requiredSteps)->filter(fn($step) => $steps[$step] ?? false)->count();

        return $requiredSteps ? round(($completedSteps / count($requiredSteps)) * 100) : 0;
    }

    /**
     * Update usage statistics.
     */
    public function updateUsageStats(array $stats): void
    {
        foreach ($stats as $key => $value) {
            if (in_array($key, ['monthly_active_users', 'total_conversations', 'ai_responses_count'])) {
                $this->$key = $value;
            }
        }
        $this->save();
    }

    /**
     * Get default onboarding steps structure.
     */
    public static function getDefaultOnboardingSteps(): array
    {
        return [
            'chatwoot_setup' => false,
            'knowledge_base' => false,
            'ai_config' => false,
        ];
    }
}
