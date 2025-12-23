<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KnowledgeDocument extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'title', 'content'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function knowledgeEmbeddings()
    {
        return $this->hasMany(KnowledgeEmbedding::class);
    }
}
