<?php

namespace App\Http\Controllers;

use App\Http\Requests\CancelSubscriptionRequest;
use App\Http\Requests\CreateBillingPortalSessionRequest;
use App\Http\Requests\CreatePaymentSheetRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\StripeBillingService;
use App\Services\SubscriptionService;
use App\Services\UsageLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly UsageLimitService $usageLimitService,
        private readonly StripeBillingService $stripeBillingService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $subscription = $this->subscriptionService->resolveActiveSubscription($user);

        return response()->json([
            'subscription' => $this->subscriptionService->serializeSubscription($subscription),
        ]);
    }

    public function usage(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        return response()->json([
            'usage' => $this->usageLimitService->snapshot($user),
        ]);
    }

    public function counters(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        return response()->json([
            'counters' => $user->usageCounters()->get(),
        ]);
    }

    public function overview(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $subscription = $this->subscriptionService->resolveActiveSubscription($user);

        return response()->json([
            'subscription' => $this->subscriptionService->serializeSubscription($subscription),
            'usage' => $this->usageLimitService->snapshot($user),
            'plans' => array_map(
                fn (Plan $plan): array => $this->planPayload($plan, $subscription),
                $this->subscriptionService->activePlans(),
            ),
            'billing' => [
                'configured' => $this->stripeBillingService->isConfigured(),
                'portal_available' => $this->stripeBillingService->canOpenPortal($user),
                'manages_current_subscription' => $subscription->provider === 'stripe',
            ],
        ]);
    }

    public function createPaymentSheet(CreatePaymentSheetRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $plan = Plan::query()
            ->where('is_active', true)
            ->findOrFail($request->validated('plan_id'));

        $paymentSheet = $this->stripeBillingService->createPaymentSheet(
            $user,
            $plan,
        );

        return response()->json([
            'payment_sheet' => $paymentSheet,
        ], 201);
    }

    public function createBillingPortalSession(CreateBillingPortalSessionRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $portal = $this->stripeBillingService->createBillingPortalSession(
            $user,
            $request->validated('return_url'),
        );

        return response()->json([
            'portal' => $portal,
        ], 201);
    }

    public function refresh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subscription_id' => ['required', 'string', 'max:255'],
        ]);

        $user = $this->authenticatedUser($request);
        $subscription = $this->stripeBillingService->refreshSubscription(
            $user,
            $validated['subscription_id'],
        );

        return response()->json([
            'subscription' => $this->subscriptionService->serializeSubscription($subscription),
        ]);
    }

    public function cancel(CancelSubscriptionRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $subscription = $this->subscriptionService->resolveActiveSubscription($user);
        $updated = $this->stripeBillingService->cancelSubscription(
            $user,
            $subscription,
            (bool) $request->validated('immediately', false),
        );

        return response()->json([
            'subscription' => $this->subscriptionService->serializeSubscription($updated),
        ]);
    }

    public function resume(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $subscription = $this->subscriptionService->resolveActiveSubscription($user);
        $updated = $this->stripeBillingService->resumeSubscription($user, $subscription);

        return response()->json([
            'subscription' => $this->subscriptionService->serializeSubscription($updated),
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
