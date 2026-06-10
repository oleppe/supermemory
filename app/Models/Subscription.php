<?php

namespace App\Models;

use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    public const ENTITLING_STATUSES = ['active', 'trialing'];

    protected $fillable = [
        'user_id',
        'plan_id',
        'provider',
        'status',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'ends_at',
        'cancel_at_period_end',
        'stripe_subscription_id',
        'stripe_checkout_session_id',
        'assigned_by',
        'assignment_note',
    ];

    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
        ];
    }

    public function scopeEntitling(Builder $query): Builder
    {
        return $query->whereIn('status', self::ENTITLING_STATUSES);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}