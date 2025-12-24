<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class TenantService
{
    /**
     * Create a new tenant with owner user
     *
     * @param array $tenantData
     * @param array $userData
     * @return array Returns ['tenant' => Tenant, 'user' => User]
     * @throws \Exception
     */
    public function createTenantWithOwner(array $tenantData, array $userData): array
    {
        return DB::transaction(function () use ($tenantData, $userData) {
            // Create tenant
            $tenant = Tenant::create([
                'name' => $tenantData['name'],
                'domain' => $tenantData['domain'],
                'ai_enabled' => $tenantData['ai_enabled'] ?? true,
                'confidence_threshold' => $tenantData['confidence_threshold'] ?? 0.7,
                'human_override_enabled' => $tenantData['human_override_enabled'] ?? true,
                'auto_escalate_threshold' => $tenantData['auto_escalate_threshold'] ?? 0.4,
            ]);

            // Create user as tenant owner
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'password' => Hash::make($userData['password']),
                'tenant_id' => $tenant->id,
            ]);

            // Update tenant with owner_id
            $tenant->update(['owner_id' => $user->id]);

            Log::info('Tenant created with owner', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'tenant_domain' => $tenant->domain,
                'user_id' => $user->id,
                'user_email' => $user->email,
            ]);

            return [
                'tenant' => $tenant,
                'user' => $user,
            ];
        });
    }

    /**
     * Validate tenant data
     *
     * @param array $data
     * @return array Validated and sanitized data
     * @throws \InvalidArgumentException
     */
    public function validateTenantData(array $data): array
    {
        $validated = [];

        // Validate tenant name
        if (empty($data['name']) || !is_string($data['name'])) {
            throw new \InvalidArgumentException('Tenant name is required and must be a string');
        }
        $validated['name'] = trim($data['name']);

        // Validate domain
        if (empty($data['domain']) || !is_string($data['domain'])) {
            throw new \InvalidArgumentException('Domain is required and must be a string');
        }

        $domain = trim($data['domain']);
        if (!preg_match('/^[a-zA-Z0-9\-]+\.[a-zA-Z]{2,}$/', $domain)) {
            throw new \InvalidArgumentException('Domain must be in format: subdomain.domain.tld');
        }

        // Check domain uniqueness
        if (Tenant::where('domain', $domain)->exists()) {
            throw new \InvalidArgumentException('Domain is already taken');
        }

        $validated['domain'] = $domain;

        // Optional AI settings with defaults
        $validated['ai_enabled'] = $data['ai_enabled'] ?? true;
        $validated['confidence_threshold'] = $data['confidence_threshold'] ?? 0.7;
        $validated['human_override_enabled'] = $data['human_override_enabled'] ?? true;
        $validated['auto_escalate_threshold'] = $data['auto_escalate_threshold'] ?? 0.4;

        return $validated;
    }

    /**
     * Validate user data
     *
     * @param array $data
     * @return array Validated and sanitized data
     * @throws \InvalidArgumentException
     */
    public function validateUserData(array $data): array
    {
        $validated = [];

        // Validate name
        if (empty($data['name']) || !is_string($data['name'])) {
            throw new \InvalidArgumentException('Name is required and must be a string');
        }
        $validated['name'] = trim($data['name']);

        // Validate email
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Valid email is required');
        }

        $email = trim(strtolower($data['email']));

        // Check email uniqueness
        if (User::where('email', $email)->exists()) {
            throw new \InvalidArgumentException('Email is already registered');
        }

        $validated['email'] = $email;

        // Validate password
        if (empty($data['password']) || !is_string($data['password'])) {
            throw new \InvalidArgumentException('Password is required');
        }

        if (strlen($data['password']) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters long');
        }

        $validated['password'] = $data['password'];

        return $validated;
    }

    /**
     * Get tenant by domain
     *
     * @param string $domain
     * @return Tenant|null
     */
    public function getTenantByDomain(string $domain): ?Tenant
    {
        return Tenant::where('domain', $domain)->first();
    }

    /**
     * Check if user is tenant owner
     *
     * @param User $user
     * @param Tenant $tenant
     * @return bool
     */
    public function isTenantOwner(User $user, Tenant $tenant): bool
    {
        return $user->id === $tenant->owner_id;
    }

    /**
     * Get tenant statistics
     *
     * @param Tenant $tenant
     * @return array
     */
    public function getTenantStats(Tenant $tenant): array
    {
        return [
            'total_users' => $tenant->users()->count(),
            'total_chatwoot_inboxes' => $tenant->chatwootInboxes()->count(),
            'total_knowledge_documents' => $tenant->knowledgeDocuments()->count(),
            'total_ai_conversations' => $tenant->aiConversations()->count(),
            'ai_enabled' => $tenant->ai_enabled,
        ];
    }
}
