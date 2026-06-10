<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_plans_endpoint_returns_active_plans_with_current_flag(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/plans')
            ->assertOk()
            ->assertJsonCount(3, 'plans')
            ->assertJsonPath('plans.0.code', 'free')
            ->assertJsonPath('plans.0.is_current', true)
            ->assertJsonPath('plans.2.code', 'pro-annual');
    }

    public function test_subscription_and_usage_endpoints_return_current_state(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/subscription')
            ->assertOk()
            ->assertJsonPath('subscription.plan.code', 'free');

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/usage')
            ->assertOk()
            ->assertJsonPath('usage.files.metric', 'files')
            ->assertJsonPath('usage.ai_questions.metric', 'ai_questions');
    }

    public function test_resolve_active_subscription_does_not_duplicate_free_plan_when_relation_is_stale(): void
    {
        $user = User::factory()->create();
        $service = app(SubscriptionService::class);

        $existing = $service->createDefaultSubscription($user);
        $user->setRelation('activeSubscription', null);

        $resolved = $service->resolveActiveSubscription($user);

        $this->assertTrue($resolved->is($existing->fresh()));
        $this->assertSame(1, Subscription::query()->where('user_id', $user->id)->count());
        $this->assertDatabaseHas('subscriptions', [
            'id' => $existing->id,
            'status' => 'active',
            'provider' => 'manual',
        ]);
    }

    public function test_non_admin_cannot_manage_plans(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/admin/plans', [
                'code' => 'team',
                'name' => 'Team',
            ])
            ->assertStatus(403);
    }

    public function test_admin_can_update_plan_and_assign_subscription(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $token = $admin->createToken('flutter')->plainTextToken;
        $pro = Plan::query()->where('code', 'pro')->firstOrFail();

        $this->withHeader('Authorization', "Bearer $token")
            ->patchJson('/api/admin/plans/'.$pro->id, [
                'daily_file_limit' => 5,
            ])
            ->assertOk()
            ->assertJsonPath('plan.limits.daily_files', 5);

        $this->withHeader('Authorization', "Bearer $token")
            ->putJson('/api/admin/users/'.$user->id.'/subscription', [
                'plan_id' => $pro->id,
                'assignment_note' => 'Manual upgrade.',
            ])
            ->assertOk()
            ->assertJsonPath('subscription.plan.code', 'pro')
            ->assertJsonPath('subscription.assignment_note', 'Manual upgrade.');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan_id' => $pro->id,
            'status' => 'active',
            'assigned_by' => $admin->id,
            'assignment_note' => 'Manual upgrade.',
        ]);
    }

    public function test_admin_can_review_plans_and_subscriptions(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['stripe_customer_id' => 'cus_test_123']);
        $token = $admin->createToken('flutter')->plainTextToken;
        $pro = Plan::query()->where('code', 'pro')->firstOrFail();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $pro->id,
            'provider' => 'stripe',
            'status' => 'active',
            'stripe_subscription_id' => 'sub_test_123',
        ]);

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/admin/plans')
            ->assertOk()
            ->assertJsonPath('plans.0.code', 'free')
            ->assertJsonStructure([
                'plans' => [['active_subscriptions_count', 'checkout_ready']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/admin/subscriptions')
            ->assertOk()
            ->assertJsonPath('subscriptions.0.provider', 'stripe')
            ->assertJsonPath('subscriptions.0.user.id', $user->id);

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/admin/users/'.$user->id.'/subscription')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('current_subscription.provider', 'stripe')
            ->assertJsonPath('subscriptions.0.plan.code', 'pro');
    }
}
