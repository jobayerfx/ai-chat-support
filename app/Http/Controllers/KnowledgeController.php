<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessKnowledgeDocument;
use App\Models\KnowledgeDocument;
use App\Services\KnowledgeProcessingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class KnowledgeController extends Controller
{
    public function __construct(
        private KnowledgeProcessingService $processingService
    ) {}

    /**
     * Upload and process a knowledge document
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240|mimes:txt,pdf,docx', // 10MB max
            'title' => 'required|string|max:255',
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

        try {
            // Extract text from uploaded file
            $textContent = $this->processingService->extractTextFromFile($request->file('file'));

            if (empty(trim($textContent))) {
                return response()->json([
                    'message' => 'No text content found in file'
                ], 400);
            }

            // Create knowledge document
            $document = KnowledgeDocument::create([
                'tenant_id' => $tenant->id,
                'title' => $request->title,
                'content' => $textContent,
            ]);

            Log::info('Knowledge document uploaded', [
                'document_id' => $document->id,
                'title' => $document->title,
                'content_length' => strlen($textContent),
                'tenant_id' => $tenant->id
            ]);

            // Dispatch processing job
            ProcessKnowledgeDocument::dispatch($document->id);

            return response()->json([
                'message' => 'Document uploaded successfully. Processing started.',
                'document' => $document
            ], 201);

        } catch (\Exception $e) {
            Log::error('Document upload failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Upload failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload text content directly
     */
    public function uploadText(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:10|max:100000', // 100KB max
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

        try {
            // Create knowledge document
            $document = KnowledgeDocument::create([
                'tenant_id' => $tenant->id,
                'title' => $request->title,
                'content' => $request->content,
            ]);

            Log::info('Knowledge document created from text', [
                'document_id' => $document->id,
                'title' => $document->title,
                'content_length' => strlen($request->content),
                'tenant_id' => $tenant->id
            ]);

            // Dispatch processing job
            ProcessKnowledgeDocument::dispatch($document->id);

            return response()->json([
                'message' => 'Document created successfully. Processing started.',
                'document' => $document
            ], 201);

        } catch (\Exception $e) {
            Log::error('Text document creation failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all knowledge documents for the tenant
     */
    public function index()
    {
        $user = Auth::user();
        $tenant = $user->tenant;

        if (!$tenant) {
            return response()->json([
                'message' => 'User not associated with a tenant'
            ], 403);
        }

        $documents = KnowledgeDocument::where('tenant_id', $tenant->id)
            ->withCount('knowledgeEmbeddings')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Documents retrieved successfully',
            'documents' => $documents
        ]);
    }

    /**
     * Get a specific document
     */
    public function show($documentId)
    {
        $user = Auth::user();
        $tenant = $user->tenant;

        if (!$tenant) {
            return response()->json([
                'message' => 'User not associated with a tenant'
            ], 403);
        }

        $document = $tenant->knowledgeDocuments()
            ->with('knowledgeEmbeddings')
            ->find($documentId);

        if (!$document) {
            return response()->json([
                'message' => 'Document not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Document retrieved successfully',
            'document' => $document
        ]);
    }

    /**
     * Delete a document and its embeddings
     */
    public function destroy($documentId)
    {
        $user = Auth::user();
        $tenant = $user->tenant;

        if (!$tenant) {
            return response()->json([
                'message' => 'User not associated with a tenant'
            ], 403);
        }

        $document = $tenant->knowledgeDocuments()->find($documentId);

        if (!$document) {
            return response()->json([
                'message' => 'Document not found'
            ], 404);
        }

        // Delete document (embeddings will be cascade deleted)
        $document->delete();

        Log::info('Knowledge document deleted', [
            'document_id' => $documentId,
            'tenant_id' => $tenant->id
        ]);

        return response()->json([
            'message' => 'Document deleted successfully'
        ]);
    }

    /**
     * Reprocess a document (regenerate embeddings)
     */
    public function reprocess($documentId)
    {
        $user = Auth::user();
        $tenant = $user->tenant;

        if (!$tenant) {
            return response()->json([
                'message' => 'User not associated with a tenant'
            ], 403);
        }

        $document = $tenant->knowledgeDocuments()->find($documentId);

        if (!$document) {
            return response()->json([
                'message' => 'Document not found'
            ], 404);
        }

        // Delete existing embeddings
        $document->knowledgeEmbeddings()->delete();

        // Dispatch processing job again
        ProcessKnowledgeDocument::dispatch($document->id);

        Log::info('Document reprocessing started', [
            'document_id' => $documentId,
            'tenant_id' => $tenant->id
        ]);

        return response()->json([
            'message' => 'Document reprocessing started'
        ]);
    }
}
