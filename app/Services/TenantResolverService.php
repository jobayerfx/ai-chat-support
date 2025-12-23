<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\ChatwootInbox;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TenantResolverService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const CACHE_PREFIX = 'tenant_inbox_';

    /**
     * Resolve tenant from Chatwoot inbox_id with caching
     *
     * @param string $inboxId
     * @return Tenant|null
     */
    public function resolveFromInboxId(string $inboxId): ?Tenant
    {
        $cacheKey = self::CACHE_PREFIX . $inboxId;

        // Check cache first
        $tenantId = Cache::get($cacheKey);

        if ($tenantId !== null) {
            $tenant = Tenant::find($tenantId);
            if ($tenant) {
                Log::debug('Tenant resolved from cache', [
                    'inbox_id' => $inboxId,
                    'tenant_id' => $tenant->id
                ]);
                return $tenant;
            }
            // Cache miss due to tenant deletion, remove stale cache
            Cache::forget($cacheKey);
        }

        // Resolve from database
        $chatwootInbox = ChatwootInbox::where('inbox_id', $inboxId)->first();

        if (!$chatwootInbox) {
            Log::warning('Chatwoot inbox not found', ['inbox_id' => $inboxId]);
            return null;
        }

        $tenant = $chatwootInbox->tenant;

        if (!$tenant) {
            Log::error('Tenant not found for inbox', [
                'inbox_id' => $inboxId,
                'chatwoot_inbox_id' => $chatwootInbox->id
            ]);
            return null;
        }

        // Cache the resolution
        Cache::put($cacheKey, $tenant->id, self::CACHE_TTL);

        Log::info('Tenant resolved from database', [
            'inbox_id' => $inboxId,
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name
        ]);

        return $tenant;
    }

    /**
     * Clear cache for a specific inbox_id
     *
     * @param string $inboxId
     * @return void
     */
    public function clearCache(string $inboxId): void
    {
        $cacheKey = self::CACHE_PREFIX . $inboxId;
        Cache::forget($cacheKey);
        Log::debug('Tenant cache cleared', ['inbox_id' => $inboxId]);
    }

    /**
     * Validate tenant access for strict isolation
     *
     * @param Tenant $tenant
     * @param int $resourceTenantId
     * @return bool
     */
    public function validateTenantAccess(Tenant $tenant, int $resourceTenantId): bool
    {
        $hasAccess = $tenant->id === $resourceTenantId;

        if (!$hasAccess) {
            Log::warning('Tenant isolation violation attempted', [
                'requesting_tenant_id' => $tenant->id,
                'resource_tenant_id' => $resourceTenantId
            ]);
        }

        return $hasAccess;
    }
}
