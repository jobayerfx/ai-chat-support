<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatwootInbox extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'inbox_id'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
