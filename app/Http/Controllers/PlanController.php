<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\StripeBillingService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly StripeBillingService $stripeBillingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $subscription = $this->subscriptionService->resolveActiveSubscription($user);

        return response()->json([
            'plans' => array_map(
                fn (Plan $plan): array => $this->planPayload($plan, $subscription),
                $this->subscriptionService->activePlans(),
            ),
        ]);
    }

    private function planPayload(Plan $plan, Subscription $subscription): array
    {
        return array_merge(
            $this->subscriptionService->serializePlan($plan),
            [
                'is_current' => $subscription->plan_id === $plan->id,
                'is_paid' => $plan->price_cents > 0,
                'can_checkout' => $this->stripeBillingService->canCheckout($plan),
            ],
        );
    }
}