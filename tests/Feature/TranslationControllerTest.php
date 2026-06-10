<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_translate_requires_a_paid_subscription(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/translate', [
                'target_language' => 'es',
                'texts' => ['Hello world'],
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'This action requires an active paid subscription.');
    }

    public function test_translate_allows_users_with_paid_subscriptions_to_reach_validation_and_controller(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;
        $subscriptionService = app(SubscriptionService::class);
        $proPlan = Plan::query()->where('code', 'pro')->firstOrFail();

        $subscriptionService->assignPlan($user, $proPlan, $admin, 'Upgrade for translation test.');

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/translate', [
                'target_language' => 'es',
                'texts' => ['Hello world'],
            ])
            ->assertStatus(500);
    }
}