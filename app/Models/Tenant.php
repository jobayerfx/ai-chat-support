<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'domain', 'ai_enabled', 'owner_id'];

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
