<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'price_cents',
        'currency',
        'stripe_price_id',
        'billing_interval_months',
        'monthly_file_limit',
        'monthly_question_limit',
        'daily_question_limit',
        'daily_file_limit',
        'max_pdf_pages',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'billing_interval_months' => 'integer',
            'monthly_file_limit' => 'integer',
            'monthly_question_limit' => 'integer',
            'daily_question_limit' => 'integer',
            'daily_file_limit' => 'integer',
            'max_pdf_pages' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}