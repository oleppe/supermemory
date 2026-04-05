<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupermemoryIngestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'source_type',
        'source_name',
        'supermemory_id',
        'supermemory_status',
        'custom_id',
        'linked_supermemory_id',
        'metadata',
        'last_synced_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
