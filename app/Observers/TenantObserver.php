<?php

namespace App\Observers;

use App\Models\Tenant;
use App\Models\TenantSettings;

class TenantObserver
{
    /**
     * Handle the Tenant "created" event.
     *
     * @param  \App\Models\Tenant  $tenant
     * @return void
     */
    public function created(Tenant $tenant)
    {
        // Auto-create tenant settings when tenant is created
        TenantSettings::create([
            'tenant_id' => $tenant->id,
            'onboarding_steps' => TenantSettings::getDefaultOnboardingSteps(),
            'primary_color' => '#4F46E5', // Default brand color
            'secondary_color' => '#6B7280',
        ]);
    }

    /**
     * Handle the Tenant "updated" event.
     *
     * @param  \App\Models\Tenant  $tenant
     * @return void
     */
    public function updated(Tenant $tenant)
    {
        //
    }

    /**
     * Handle the Tenant "deleted" event.
     *
     * @param  \App\Models\Tenant  $tenant
     * @return void
     */
    public function deleted(Tenant $tenant)
    {
        //
    }

    /**
     * Handle the Tenant "restored" event.
     *
     * @param  \App\Models\Tenant  $tenant
     * @return void
     */
    public function restored(Tenant $tenant)
    {
        //
    }

    /**
     * Handle the Tenant "force deleted" event.
     *
     * @param  \App\Models\Tenant  $tenant
     * @return void
     */
    public function forceDeleted(Tenant $tenant)
    {
        //
    }
}
