<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'price_cents' => 0,
            'currency' => 'USD',
            'stripe_price_id' => null,
            'billing_interval_months' => 1,
            'monthly_file_limit' => 10,
            'monthly_question_limit' => 25,
            'daily_question_limit' => null,
            'daily_file_limit' => null,
            'max_pdf_pages' => 10,
            'is_active' => true,
            'sort_order' => 1,
        ];
    }
}