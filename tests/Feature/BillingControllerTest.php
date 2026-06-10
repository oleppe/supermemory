<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MocksStripeBillingService;
use Tests\TestCase;

class BillingControllerTest extends TestCase
{
    use MocksStripeBillingService;
    use RefreshDatabase;

    public function test_billing_overview_returns_subscription_usage_plans_and_billing_flags(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/billing/overview')
            ->assertOk()
            ->assertJsonPath('subscription.plan.code', 'free')
            ->assertJsonPath('usage.files.metric', 'files')
            ->assertJsonPath('plans.0.code', 'free')
            ->assertJsonPath('plans.0.can_checkout', false)
            ->assertJsonPath('billing.configured', false)
            ->assertJsonPath('billing.portal_available', false);
    }

    public function test_customer_can_create_payment_sheet_for_paid_plan(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;
        $plan = Plan::query()->where('code', 'pro')->firstOrFail();
        $stripe = $this->mockStripeBillingService();

        $stripe->expects($this->once())
            ->method('createPaymentSheet')
            ->with(
                $this->callback(fn (User $resolvedUser): bool => $resolvedUser->is($user)),
                $this->callback(fn (Plan $resolvedPlan): bool => $resolvedPlan->is($plan)),
            )
            ->willReturn([
                'customer_id' => 'cus_test_123',
                'ephemeral_key_secret' => 'ek_test_123',
                'payment_intent_client_secret' => 'pi_test_secret_123',
                'subscription_id' => 'sub_test_123',
            ]);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/subscription/payment-sheet', [
                'plan_id' => $plan->id,
            ])
            ->assertCreated()
            ->assertJsonPath('payment_sheet.customer_id', 'cus_test_123')
            ->assertJsonPath('payment_sheet.ephemeral_key_secret', 'ek_test_123')
            ->assertJsonPath('payment_sheet.payment_intent_client_secret', 'pi_test_secret_123')
            ->assertJsonPath('payment_sheet.subscription_id', 'sub_test_123');
    }

    public function test_customer_can_create_billing_portal_session(): void
    {
        $user = User::factory()->create(['stripe_customer_id' => 'cus_test_123']);
        $token = $user->createToken('flutter')->plainTextToken;
        $stripe = $this->mockStripeBillingService();

        $stripe->expects($this->once())
            ->method('createBillingPortalSession')
            ->with(
                $this->callback(fn (User $resolvedUser): bool => $resolvedUser->is($user)),
                'https://app.example.test/account',
            )
            ->willReturn([
                'url' => 'https://billing.stripe.com/p/session/test',
            ]);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/subscription/portal-session', [
                'return_url' => 'https://app.example.test/account',
            ])
            ->assertCreated()
            ->assertJsonPath('portal.url', 'https://billing.stripe.com/p/session/test');
    }

    public function test_customer_can_refresh_stripe_subscription_after_payment_confirmation(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;
        $plan = Plan::query()->where('code', 'pro')->firstOrFail();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'provider' => 'stripe',
            'status' => 'incomplete',
            'stripe_subscription_id' => 'sub_test_123',
        ]);
        $refreshedSubscription = $subscription->replicate()->forceFill([
            'id' => $subscription->id,
            'plan_id' => $plan->id,
            'user_id' => $user->id,
            'provider' => 'stripe',
            'status' => 'active',
            'stripe_subscription_id' => 'sub_test_123',
        ]);
        $refreshedSubscription->setRelation('plan', $plan);
        $stripe = $this->mockStripeBillingService();

        $stripe->expects($this->once())
            ->method('refreshSubscription')
            ->with(
                $this->callback(fn (User $resolvedUser): bool => $resolvedUser->is($user)),
                'sub_test_123',
            )
            ->willReturn($refreshedSubscription);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/subscription/refresh', [
                'subscription_id' => 'sub_test_123',
            ])
            ->assertOk()
            ->assertJsonPath('subscription.provider', 'stripe')
            ->assertJsonPath('subscription.status', 'active')
            ->assertJsonPath('subscription.plan.code', 'pro');
    }

    public function test_customer_can_cancel_and_resume_stripe_subscription(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;
        $plan = Plan::query()->where('code', 'pro')->firstOrFail();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'provider' => 'stripe',
            'status' => 'active',
            'stripe_subscription_id' => 'sub_test_123',
            'stripe_checkout_session_id' => 'cs_test_123',
        ]);
        $cancelledSubscription = $subscription->replicate()->forceFill([
            'id' => $subscription->id,
            'plan_id' => $plan->id,
            'user_id' => $user->id,
            'provider' => 'stripe',
            'status' => 'active',
            'cancel_at_period_end' => true,
            'stripe_subscription_id' => 'sub_test_123',
            'stripe_checkout_session_id' => 'cs_test_123',
        ]);
        $cancelledSubscription->setRelation('plan', $plan);

        $resumedSubscription = $subscription->replicate()->forceFill([
            'id' => $subscription->id,
            'plan_id' => $plan->id,
            'user_id' => $user->id,
            'provider' => 'stripe',
            'status' => 'active',
            'cancel_at_period_end' => false,
            'stripe_subscription_id' => 'sub_test_123',
            'stripe_checkout_session_id' => 'cs_test_123',
        ]);
        $resumedSubscription->setRelation('plan', $plan);
        $stripe = $this->mockStripeBillingService();

        $stripe->expects($this->once())
            ->method('cancelSubscription')
            ->with(
                $this->callback(fn (User $resolvedUser): bool => $resolvedUser->is($user)),
                $this->callback(fn (Subscription $resolvedSubscription): bool => $resolvedSubscription->id === $subscription->id),
                false,
            )
            ->willReturn($cancelledSubscription);

        $stripe->expects($this->once())
            ->method('resumeSubscription')
            ->with(
                $this->callback(fn (User $resolvedUser): bool => $resolvedUser->is($user)),
                $this->callback(fn (Subscription $resolvedSubscription): bool => $resolvedSubscription->id === $subscription->id),
            )
            ->willReturn($resumedSubscription);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/subscription/cancel')
            ->assertOk()
            ->assertJsonPath('subscription.provider', 'stripe')
            ->assertJsonPath('subscription.cancel_at_period_end', true);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/subscription/resume')
            ->assertOk()
            ->assertJsonPath('subscription.provider', 'stripe')
            ->assertJsonPath('subscription.cancel_at_period_end', false);
    }
}
