<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Services\StripeBillingService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPlanController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly StripeBillingService $stripeBillingService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'active_only' => ['nullable', 'boolean'],
        ]);

        $plans = Plan::query()
            ->when($validated['active_only'] ?? false, fn ($query) => $query->where('is_active', true))
            ->withCount([
                'subscriptions as active_subscriptions_count' => fn ($query) => $query->whereIn('status', Subscription::ENTITLING_STATUSES),
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'plans' => array_map(function (Plan $plan): array {
                return array_merge(
                    $this->subscriptionService->serializeAdminPlan($plan),
                    ['checkout_ready' => $this->stripeBillingService->canCheckout($plan)],
                );
            }, $plans->items()),
            'meta' => [
                'current_page' => $plans->currentPage(),
                'last_page' => $plans->lastPage(),
                'per_page' => $plans->perPage(),
                'total' => $plans->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());

        $plan = Plan::query()->create($validated);

        return response()->json([
            'plan' => $this->subscriptionService->serializePlan($plan),
        ], 201);
    }

    public function update(Request $request, Plan $plan): JsonResponse
    {
        $validated = $request->validate($this->rules(false));
        $plan->fill($validated)->save();

        return response()->json([
            'plan' => $this->subscriptionService->serializePlan($plan->fresh()),
        ]);
    }

    private function rules(bool $creating = true): array
    {
        return [
            'code' => [$creating ? 'required' : 'sometimes', 'string', 'max:50', 'alpha_dash', 'unique:plans,code'.($creating ? '' : ','.$this->routePlanId())],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'price_cents' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'stripe_price_id' => ['nullable', 'string', 'max:100', 'unique:plans,stripe_price_id'.($creating ? '' : ','.$this->routePlanId())],
            'billing_interval_months' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'monthly_file_limit' => ['nullable', 'integer', 'min:0'],
            'monthly_question_limit' => ['nullable', 'integer', 'min:0'],
            'daily_question_limit' => ['nullable', 'integer', 'min:0'],
            'daily_file_limit' => ['nullable', 'integer', 'min:0'],
            'max_pdf_pages' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    private function routePlanId(): int
    {
        /** @var Plan|null $plan */
        $plan = request()->route('plan');

        return $plan?->id ?? 0;
    }
}