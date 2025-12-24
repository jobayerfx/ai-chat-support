<?php

namespace App\Http\Controllers;

use App\Services\ChatwootConnectionService;
use App\Services\AiEnablementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class OnboardingController extends Controller
{
    public function __construct(
        private ChatwootConnectionService $chatwootService,
        private AiEnablementService $aiService
    ) {}

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

    /**
     * Connect Chatwoot for onboarding
     */
    public function connectChatwoot(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'chatwoot_url' => 'required|url',
            'account_id' => 'required|integer',
            'access_token' => 'required|string|min:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $tenant = $request->tenant; // Added by TenantScopeMiddleware

        // Test the connection
        $account = $this->chatwootService->testConnection(
            $request->chatwoot_url,
            $request->access_token
        );

        if (!$account) {
            return response()->json([
                'message' => 'Invalid Chatwoot credentials. Please check your URL and access token.'
            ], 400);
        }

        // Verify account ID matches
        if ($account['id'] !== (int)$request->account_id) {
            return response()->json([
                'message' => 'Account ID does not match the provided access token.'
            ], 400);
        }

        // Get or create tenant settings
        $settings = $tenant->settings ?? $tenant->settings()->create([]);

        // Store encrypted token and update settings
        $settings->update([
            'chatwoot_base_url' => $request->chatwoot_url,
            'chatwoot_api_token' => $request->access_token, // Auto-encrypted by mutator
            'chatwoot_connected' => true,
        ]);

        // Mark onboarding step as completed
        $settings->completeOnboardingStep('chatwoot_setup');

        return response()->json([
            'message' => 'Chatwoot connected successfully',
            'account' => [
                'id' => $account['id'],
                'name' => $account['name'],
            ]
        ]);
    }

    /**
     * Enable or disable AI for the tenant
     */
    public function enableAi(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $tenant = $request->tenant; // Added by TenantScopeMiddleware

        $result = $this->aiService->toggleAi($tenant, $request->enabled);

        $statusCode = $result['success'] ? 200 : 400;

        return response()->json($result, $statusCode);
    }
}
