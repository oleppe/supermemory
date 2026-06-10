<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\SupermemoryService;
use App\Services\UsageLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly SupermemoryService $supermemoryService,
        private readonly UsageLimitService $usageLimitService,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:3'],
            'preferred_language' => $this->preferredLanguageRules(),
        ]);

        $user = User::create([
            'name' => explode('@', $validated['email'])[0],
            'email' => $validated['email'],
            'preferred_language' => $validated['preferred_language'] ?? null,
            'password' => $validated['password'],
        ]);

        $subscription = $this->subscriptionService->createDefaultSubscription(
            $user,
            note: 'Assigned on registration.',
        );

        $token = $user->createToken('flutter')->plainTextToken;

        return response()->json([
            ...$this->authPayload($user, $subscription),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        $token = $user->createToken('flutter')->plainTextToken;
        $subscription = $this->subscriptionService->resolveActiveSubscription($user);

        return response()->json([
            ...$this->authPayload($user, $subscription),
            'token' => $token,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $subscription = $this->subscriptionService->resolveActiveSubscription($user);

        return response()->json([
            ...$this->authPayload($user, $subscription),
            'supermemory' => [
                'configured' => $this->supermemoryService->isConfigured(),
                'container_tag' => $this->supermemoryContainerTag($user),
            ],
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferred_language' => ['present', ...$this->preferredLanguageRules()],
        ]);

        $user = $this->authenticatedUser($request);

        $user->forceFill([
            'preferred_language' => $validated['preferred_language'] ?? null,
        ])->save();

        $subscription = $this->subscriptionService->resolveActiveSubscription($user);

        return response()->json($this->authPayload($user->fresh(), $subscription));
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $this->authenticatedUser($request)->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out']);
    }

    private function authPayload(User $user, \App\Models\Subscription $subscription): array
    {
        return [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'preferred_language' => $user->preferred_language,
                'is_admin' => $user->is_admin,
            ],
            'subscription' => $this->subscriptionService->serializeSubscription($subscription),
            'usage' => $this->usageLimitService->snapshot($user),
        ];
    }

    private function preferredLanguageRules(): array
    {
        return [
            'nullable',
            'string',
            'max:16',
            'regex:/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/',
        ];
    }
}
