<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PlanSeeder::class);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $freePlan = Plan::query()->where('code', 'free')->first();

        if ($freePlan instanceof Plan) {
            Subscription::query()->updateOrCreate(
                ['user_id' => $user->id, 'status' => 'active'],
                [
                    'plan_id' => $freePlan->id,
                    'current_period_start' => now()->startOfDay(),
                    'current_period_end' => now()->startOfDay()->addMonths($freePlan->billing_interval_months),
                    'assignment_note' => 'Seeded default subscription.',
                ],
            );
        }
    }
}
