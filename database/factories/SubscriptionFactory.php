<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $start = now()->startOfDay();

        return [
            'user_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'provider' => 'manual',
            'status' => 'active',
            'current_period_start' => $start,
            'current_period_end' => $start->copy()->addMonth(),
            'cancelled_at' => null,
            'ends_at' => null,
            'cancel_at_period_end' => false,
            'stripe_subscription_id' => null,
            'stripe_checkout_session_id' => null,
            'assigned_by' => null,
            'assignment_note' => null,
        ];
    }
}