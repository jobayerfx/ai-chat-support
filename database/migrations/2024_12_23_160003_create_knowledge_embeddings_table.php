<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS vector;');
        }
        Schema::create('knowledge_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_document_id')->constrained('knowledge_documents')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            if (DB::getDriverName() === 'pgsql') {
                // For PostgreSQL with pgvector
                DB::statement('ALTER TABLE knowledge_embeddings ADD COLUMN embedding vector(1536);');
            } else {
                // For other databases, store as JSON
                $table->json('embedding');
            }
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_embeddings');
        DB::statement('DROP EXTENSION IF EXISTS vector;');
    }
};
