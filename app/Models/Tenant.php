<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'domain',
        'ai_enabled',
        'owner_id',
        'confidence_threshold',
        'human_override_enabled',
        'auto_escalate_threshold'
    ];

    protected $casts = [
        'ai_enabled' => 'boolean',
        'confidence_threshold' => 'decimal:2',
        'human_override_enabled' => 'boolean',
        'auto_escalate_threshold' => 'decimal:2',
    ];

    public function chatwootInboxes()
    {
        return $this->hasMany(ChatwootInbox::class);
    }

    public function knowledgeDocuments()
    {
        return $this->hasMany(KnowledgeDocument::class);
    }

    public function aiConversations()
    {
        return $this->hasMany(AIConversation::class);
    }

    public function aiUsageLogs()
    {
        return $this->hasMany(AIUsageLog::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
