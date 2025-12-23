<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIMessage extends Model
{
    use HasFactory;

    protected $fillable = ['ai_conversation_id', 'sender', 'content'];

    protected $casts = [
        'sender' => 'string',
    ];

    public function aiConversation()
    {
        return $this->belongsTo(AIConversation::class);
    }
}
