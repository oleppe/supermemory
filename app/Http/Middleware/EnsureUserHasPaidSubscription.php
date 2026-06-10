<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPaidSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $subscriptionService = app(SubscriptionService::class);
        $user = $request->user();

        if (! $user) {
            return new JsonResponse([
                'message' => 'Authentication is required.',
            ], 401);
        }

        $subscription = $subscriptionService->resolveActiveSubscription($user);

        if ($subscription->plan->price_cents < 1) {
            return new JsonResponse([
                'message' => 'This action requires an active paid subscription.',
            ], 403);
        }

        return $next($request);
    }
}
