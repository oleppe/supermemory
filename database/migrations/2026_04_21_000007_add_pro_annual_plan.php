<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();

        DB::table('plans')->updateOrInsert(
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
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('plans')
            ->where('code', 'pro-annual')
            ->delete();
    }
};