<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeDocument;
use App\Jobs\ProcessKnowledgeDocument;
use App\Services\KnowledgeSearchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class SimpleKnowledgeController extends Controller
{
    public function __construct(
        private KnowledgeSearchService $searchService
    ) {}

    /**
     * Perform semantic search on knowledge base
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|max:1000',
            'limit' => 'nullable|integer|min:1|max:20',
            'threshold' => 'nullable|numeric|min:0|max:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $tenant = $request->tenant; // Added by TenantScopeMiddleware

        $limit = $request->input('limit', 5);
        $threshold = $request->input('threshold', 0.0);

        $results = $this->searchService->search(
            $request->input('query'),
            $tenant->id,
            $limit,
            $threshold
        );

        return response()->json([
            'message' => 'Search completed successfully',
            'data' => $results
        ]);
    }

    /**
     * Create a new knowledge document and dispatch processing job
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:100000', // 100KB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $tenant = $request->tenant; // Added by TenantScopeMiddleware

        $document = KnowledgeDocument::create([
            'tenant_id' => $tenant->id,
            'title' => $request->title,
            'content' => $request->content,
        ]);

        // Dispatch the async processing job
        ProcessKnowledgeDocument::dispatch($document->id);

        return response()->json([
            'message' => 'Document created successfully. Processing started.',
            'document' => $document
        ], 201);
    }

    /**
     * Get all knowledge documents for the tenant
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->tenant; // Added by TenantScopeMiddleware

        $documents = KnowledgeDocument::where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Documents retrieved successfully',
            'documents' => $documents
        ]);
    }

    /**
     * Delete a knowledge document
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $tenant = $request->tenant; // Added by TenantScopeMiddleware

        $document = KnowledgeDocument::where('tenant_id', $tenant->id)
            ->find($id);

        if (!$document) {
            return response()->json([
                'message' => 'Document not found'
            ], 404);
        }

        $document->delete();

        return response()->json([
            'message' => 'Document deleted successfully'
        ]);
    }
}
