<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OnboardingController extends Controller
{
    /**
     * Get the onboarding status for the authenticated user's tenant.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || !$user->tenant) {
            return response()->json([
                'error' => 'No tenant found for user'
            ], 404);
        }

        $tenant = $user->tenant;
        $settings = $tenant->settings;

        return response()->json([
            'chatwoot_connected' => $settings?->chatwoot_connected ?? false,
            'ai_enabled' => $tenant->ai_enabled,
            'completed' => $settings?->onboarding_completed ?? false,
        ]);
    }
}
