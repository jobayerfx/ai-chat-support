<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIUsageLog extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'conversation_id', 'tokens_used', 'cost', 'decision'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
