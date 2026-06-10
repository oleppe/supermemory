<?php

namespace Database\Factories;

use App\Models\UsageCounter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageCounter>
 */
class UsageCounterFactory extends Factory
{
    protected $model = UsageCounter::class;

    public function definition(): array
    {
        $start = now()->startOfMonth();

        return [
            'user_id' => User::factory(),
            'metric' => 'ai_questions',
            'period' => 'billing_cycle',
            'period_start' => $start,
            'period_end' => $start->copy()->addMonth(),
            'used' => 0,
            'last_used_at' => null,
        ];
    }
}