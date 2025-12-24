<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KnowledgeEmbedding extends Model
{
    use HasFactory;

    protected $fillable = ['knowledge_document_id', 'tenant_id', 'chunk_text', 'chunk_index', 'embedding'];

    protected $casts = [
        'embedding' => 'array', // Cast JSON to array for pgvector operations
    ];

    public function knowledgeDocument()
    {
        return $this->belongsTo(KnowledgeDocument::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
