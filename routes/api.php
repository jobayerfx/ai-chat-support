<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatwootWebhookController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// SPA CSRF protection
Route::get('/sanctum/csrf-cookie', function (Request $request) {
    return response()->json(['message' => 'CSRF cookie set']);
});

// Auth routes for SPA
Route::post('/register', [App\Http\Controllers\Auth\RegisterController::class, 'register']);
Route::post('/login', [App\Http\Controllers\Auth\LoginController::class, 'login']);
Route::middleware('auth:sanctum')->post('/logout', [App\Http\Controllers\Auth\LoginController::class, 'logout']);

// Chatwoot connection wizard
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('chatwoot')->group(function () {
        Route::post('/test-connection', [App\Http\Controllers\ChatwootConnectionController::class, 'testConnection']);
        Route::post('/inboxes', [App\Http\Controllers\ChatwootConnectionController::class, 'getInboxes']);
        Route::post('/connect', [App\Http\Controllers\ChatwootConnectionController::class, 'connect']);
        Route::get('/connections', [App\Http\Controllers\ChatwootConnectionController::class, 'getConnections']);
        Route::delete('/connections/{connectionId}', [App\Http\Controllers\ChatwootConnectionController::class, 'disconnect']);
    });

    // Knowledge management
    Route::prefix('knowledge')->group(function () {
        Route::post('/upload', [App\Http\Controllers\KnowledgeController::class, 'upload']);
        Route::post('/upload-text', [App\Http\Controllers\KnowledgeController::class, 'uploadText']);
        Route::get('/', [App\Http\Controllers\KnowledgeController::class, 'index']);
        Route::get('/{documentId}', [App\Http\Controllers\KnowledgeController::class, 'show']);
        Route::delete('/{documentId}', [App\Http\Controllers\KnowledgeController::class, 'destroy']);
        Route::post('/{documentId}/reprocess', [App\Http\Controllers\KnowledgeController::class, 'reprocess']);
    });

    // AI Configuration
    Route::prefix('ai-config')->group(function () {
        Route::get('/', [App\Http\Controllers\TenantAIConfigController::class, 'show']);
        Route::put('/', [App\Http\Controllers\TenantAIConfigController::class, 'update']);
        Route::post('/reset', [App\Http\Controllers\TenantAIConfigController::class, 'reset']);
    });

    // Onboarding status
    Route::middleware('tenant.scope')->get('/onboarding/status', [App\Http\Controllers\OnboardingController::class, 'status']);
});

// Chatwoot webhook
Route::post('/webhooks/chatwoot', [ChatwootWebhookController::class, 'handle']);
