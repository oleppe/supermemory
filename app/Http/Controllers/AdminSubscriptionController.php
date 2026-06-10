<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:50'],
            'plan_id' => ['nullable', 'integer', 'exists:plans,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $subscriptions = Subscription::query()
            ->with(['plan', 'user'])
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['plan_id'] ?? null, fn ($query, $planId) => $query->where('plan_id', $planId))
            ->when($validated['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($validated['search'] ?? null, function ($query, $search): void {
                $query->whereHas('user', function ($userQuery) use ($search): void {
                    $userQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->latest('id')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'subscriptions' => array_map(
                fn (Subscription $subscription): array => $this->subscriptionService->serializeAdminSubscription($subscription),
                $subscriptions->items(),
            ),
            'meta' => [
                'current_page' => $subscriptions->currentPage(),
                'last_page' => $subscriptions->lastPage(),
                'per_page' => $subscriptions->perPage(),
                'total' => $subscriptions->total(),
            ],
        ]);
    }

    public function show(User $user): JsonResponse
    {
        $subscriptions = $user->subscriptions()
            ->with('plan')
            ->latest('id')
            ->limit(10)
            ->get();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->is_admin,
            ],
            'current_subscription' => $subscriptions->first() instanceof Subscription
                ? $this->subscriptionService->serializeAdminSubscription($subscriptions->first())
                : null,
            'subscriptions' => $subscriptions
                ->map(fn (Subscription $subscription): array => $this->subscriptionService->serializeAdminSubscription($subscription))
                ->values()
                ->all(),
        ]);
    }

    public function assign(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'assignment_note' => ['nullable', 'string', 'max:255'],
        ]);

        $plan = Plan::query()->findOrFail($validated['plan_id']);
        $admin = $this->authenticatedUser($request);
        $subscription = $this->subscriptionService->assignPlan(
            $user,
            $plan,
            $admin,
            $validated['assignment_note'] ?? 'Updated by administrator.',
        );

        return response()->json([
            'subscription' => $this->subscriptionService->serializeSubscription($subscription),
        ]);
    }
}