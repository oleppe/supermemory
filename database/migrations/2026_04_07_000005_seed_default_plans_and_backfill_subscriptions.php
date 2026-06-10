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
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('plans')->updateOrInsert(
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
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        $freePlanId = DB::table('plans')->where('code', 'free')->value('id');

        if ($freePlanId === null) {
            return;
        }

        $usersWithoutSubscription = DB::table('users')
            ->leftJoin('subscriptions', function ($join): void {
                $join->on('users.id', '=', 'subscriptions.user_id')
                    ->where('subscriptions.status', '=', 'active');
            })
            ->whereNull('subscriptions.id')
            ->select('users.id')
            ->get();

        foreach ($usersWithoutSubscription as $user) {
            $periodStart = $now->copy()->startOfDay();

            DB::table('subscriptions')->insert([
                'user_id' => $user->id,
                'plan_id' => $freePlanId,
                'status' => 'active',
                'current_period_start' => $periodStart,
                'current_period_end' => $periodStart->copy()->addMonth(),
                'cancelled_at' => null,
                'ends_at' => null,
                'assigned_by' => null,
                'assignment_note' => 'Migrated default free subscription.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('subscriptions')
            ->where('assignment_note', 'Migrated default free subscription.')
            ->delete();

        DB::table('plans')
            ->whereIn('code', ['free', 'pro'])
            ->delete();
    }
};
