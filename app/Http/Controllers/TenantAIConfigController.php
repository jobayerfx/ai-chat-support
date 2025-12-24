<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TenantAIConfigController extends Controller
{
    /**
     * Get AI configuration for the tenant
     */
    public function show()
    {
        $user = Auth::user();
        $tenant = $user->tenant;

        if (!$tenant) {
            return response()->json([
                'message' => 'User not associated with a tenant'
            ], 403);
        }

        return response()->json([
            'message' => 'AI configuration retrieved successfully',
            'config' => [
                'ai_enabled' => $tenant->ai_enabled,
                'confidence_threshold' => $tenant->confidence_threshold,
                'human_override_enabled' => $tenant->human_override_enabled,
                'auto_escalate_threshold' => $tenant->auto_escalate_threshold,
            ]
        ]);
    }

    /**
     * Update AI configuration for the tenant
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ai_enabled' => 'boolean',
            'confidence_threshold' => 'numeric|min:0|max:1',
            'human_override_enabled' => 'boolean',
            'auto_escalate_threshold' => 'numeric|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $tenant = $user->tenant;

        if (!$tenant) {
            return response()->json([
                'message' => 'User not associated with a tenant'
            ], 403);
        }

        // Validate threshold relationships
        $confidenceThreshold = $request->input('confidence_threshold', $tenant->confidence_threshold);
        $autoEscalateThreshold = $request->input('auto_escalate_threshold', $tenant->auto_escalate_threshold);

        if ($autoEscalateThreshold >= $confidenceThreshold) {
            return response()->json([
                'message' => 'Auto-escalate threshold must be less than confidence threshold',
                'errors' => [
                    'auto_escalate_threshold' => ['Must be less than confidence threshold']
                ]
            ], 422);
        }

        try {
            $tenant->update([
                'ai_enabled' => $request->input('ai_enabled', $tenant->ai_enabled),
                'confidence_threshold' => $confidenceThreshold,
                'human_override_enabled' => $request->input('human_override_enabled', $tenant->human_override_enabled),
                'auto_escalate_threshold' => $autoEscalateThreshold,
            ]);

            Log::info('Tenant AI configuration updated', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'config' => $tenant->only([
                    'ai_enabled',
                    'confidence_threshold',
                    'human_override_enabled',
                    'auto_escalate_threshold'
                ])
            ]);

            return response()->json([
                'message' => 'AI configuration updated successfully',
                'config' => [
                    'ai_enabled' => $tenant->ai_enabled,
                    'confidence_threshold' => $tenant->confidence_threshold,
                    'human_override_enabled' => $tenant->human_override_enabled,
                    'auto_escalate_threshold' => $tenant->auto_escalate_threshold,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update tenant AI configuration', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to update AI configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset AI configuration to defaults
     */
    public function reset()
    {
        $user = Auth::user();
        $tenant = $user->tenant;

        if (!$tenant) {
            return response()->json([
                'message' => 'User not associated with a tenant'
            ], 403);
        }

        try {
            $tenant->update([
                'ai_enabled' => true,
                'confidence_threshold' => 0.7,
                'human_override_enabled' => true,
                'auto_escalate_threshold' => 0.4,
            ]);

            Log::info('Tenant AI configuration reset to defaults', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id
            ]);

            return response()->json([
                'message' => 'AI configuration reset to defaults',
                'config' => [
                    'ai_enabled' => $tenant->ai_enabled,
                    'confidence_threshold' => $tenant->confidence_threshold,
                    'human_override_enabled' => $tenant->human_override_enabled,
                    'auto_escalate_threshold' => $tenant->auto_escalate_threshold,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to reset tenant AI configuration', [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to reset AI configuration',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
