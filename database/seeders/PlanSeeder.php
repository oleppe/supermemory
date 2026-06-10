<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::query()->updateOrCreate(
            ['code' => 'free'],
            [
                'name' => 'Free / Discovery',
                'description' => 'Light usage to help users adopt the product.',
                'price_cents' => 0,
                'currency' => 'USD',
                'billing_interval_months' => 1,
                'monthly_file_limit' => 10,
                'monthly_question_limit' => 25,
                'daily_question_limit' => null,
                'daily_file_limit' => null,
                'max_pdf_pages' => 10,
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        Plan::query()->updateOrCreate(
            ['code' => 'pro'],
            [
                'name' => 'Pro / Unlimited-ish',
                'description' => 'Paid plan for heavier usage with abuse protection.',
                'price_cents' => 999,
                'currency' => 'USD',
                'billing_interval_months' => 1,
                'monthly_file_limit' => 150,
                'monthly_question_limit' => 400,
                'daily_question_limit' => 50,
                'daily_file_limit' => null,
                'max_pdf_pages' => 10,
                'is_active' => true,
                'sort_order' => 2,
            ],
        );

        Plan::query()->updateOrCreate(
            ['code' => 'pro-annual'],
            [
                'name' => 'Pro / Annual',
                'description' => 'Annual Pro plan with the same usage limits billed at $100 per year.',
                'price_cents' => 10000,
                'currency' => 'USD',
                'billing_interval_months' => 12,
                'monthly_file_limit' => 150,
                'monthly_question_limit' => 400,
                'daily_question_limit' => 50,
                'daily_file_limit' => null,
                'max_pdf_pages' => 10,
                'is_active' => true,
                'sort_order' => 3,
            ],
        );
    }
}
