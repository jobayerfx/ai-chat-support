<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KnowledgeEmbedding extends Model
{
    use HasFactory;

    protected $fillable = ['knowledge_document_id', 'tenant_id', 'embedding'];

    public function knowledgeDocument()
    {
        return $this->belongsTo(KnowledgeDocument::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
