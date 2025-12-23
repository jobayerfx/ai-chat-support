<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIConversation extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'conversation_id', 'messages', 'ai_active'];

    protected $casts = [
        'messages' => 'array',
        'ai_active' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function aiMessages()
    {
        return $this->hasMany(AIMessage::class);
    }
}
