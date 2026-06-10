<?php

namespace Tests\Unit;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\StripeBillingService;
use App\Services\SubscriptionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Stripe\Subscription as StripeSubscription;
use Tests\TestCase;

class StripeBillingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolve_subscription_id_from_invoice_line_item_when_top_level_field_is_missing(): void
    {
        $service = app(StripeBillingService::class);
        $resolveSubscriptionId = new ReflectionMethod($service, 'resolveSubscriptionIdFromInvoicePayload');
        $resolveSubscriptionId->setAccessible(true);

        $subscriptionId = $resolveSubscriptionId->invoke($service, [
            'id' => 'in_test_123',
            'lines' => [
                'data' => [[
                    'parent' => [
                        'subscription_item_details' => [
                            'subscription' => 'sub_test_123',
                        ],
                    ],
                ]],
            ],
        ]);

        $this->assertSame('sub_test_123', $subscriptionId);
    }

    public function test_sync_ignores_stale_incomplete_updates_after_activation(): void
    {
        $user = User::factory()->create([
            'stripe_customer_id' => 'cus_test_123',
        ]);
        $freeSubscription = app(SubscriptionService::class)->createDefaultSubscription($user);
        $plan = Plan::query()->where('code', 'pro')->firstOrFail();
        $plan->forceFill([
            'stripe_price_id' => 'price_test_123',
        ])->save();

        $service = app(StripeBillingService::class);
        $syncStripeSubscription = new ReflectionMethod($service, 'syncStripeSubscription');
        $syncStripeSubscription->setAccessible(true);
        $periodStart = CarbonImmutable::now()->startOfDay();
        $periodEnd = $periodStart->addMonth();

        $activeStripeSubscription = StripeSubscription::constructFrom([
            'id' => 'sub_test_123',
            'status' => 'active',
            'customer' => 'cus_test_123',
            'current_period_start' => $periodStart->timestamp,
            'current_period_end' => $periodEnd->timestamp,
            'cancel_at_period_end' => false,
            'metadata' => [
                'user_id' => (string) $user->id,
                'plan_id' => (string) $plan->id,
            ],
            'items' => [
                'data' => [[
                    'price' => [
                        'id' => 'price_test_123',
                    ],
                ]],
            ],
        ]);

        $staleIncompleteStripeSubscription = StripeSubscription::constructFrom([
            'id' => 'sub_test_123',
            'status' => 'incomplete',
            'customer' => 'cus_test_123',
            'current_period_start' => $periodStart->timestamp,
            'current_period_end' => $periodEnd->timestamp,
            'cancel_at_period_end' => false,
            'metadata' => [
                'user_id' => (string) $user->id,
                'plan_id' => (string) $plan->id,
            ],
            'items' => [
                'data' => [[
                    'price' => [
                        'id' => 'price_test_123',
                    ],
                ]],
            ],
        ]);

        $syncStripeSubscription->invoke($service, $activeStripeSubscription, 'cs_test_123');
        $syncStripeSubscription->invoke($service, $staleIncompleteStripeSubscription, 'cs_test_123');

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'provider' => 'stripe',
            'status' => 'active',
            'stripe_subscription_id' => 'sub_test_123',
            'stripe_checkout_session_id' => 'cs_test_123',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $freeSubscription->id,
            'status' => 'replaced',
        ]);

        $this->assertSame(2, Subscription::query()->where('user_id', $user->id)->count());
    }
}
