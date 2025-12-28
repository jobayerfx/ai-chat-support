<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add vector extension if not exists (in case it wasn't created)
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector;');

        // Create HNSW index for efficient similarity search
        // This index uses cosine distance for similarity queries
        DB::statement('CREATE INDEX knowledge_embeddings_embedding_idx ON knowledge_embeddings USING hnsw (embedding vector_cosine_ops);');

        // Create regular B-tree indexes for foreign keys and common queries
        DB::statement('CREATE INDEX knowledge_embeddings_tenant_id_idx ON knowledge_embeddings (tenant_id);');
        DB::statement('CREATE INDEX knowledge_embeddings_knowledge_document_id_idx ON knowledge_embeddings (knowledge_document_id);');
        DB::statement('CREATE INDEX knowledge_embeddings_tenant_document_idx ON knowledge_embeddings (tenant_id, knowledge_document_id);');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop indexes
        DB::statement('DROP INDEX IF EXISTS knowledge_embeddings_embedding_idx;');
        DB::statement('DROP INDEX IF EXISTS knowledge_embeddings_tenant_id_idx;');
        DB::statement('DROP INDEX IF EXISTS knowledge_embeddings_knowledge_document_id_idx;');
        DB::statement('DROP INDEX IF EXISTS knowledge_embeddings_tenant_document_idx;');
    }
};
