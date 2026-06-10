<?php

namespace App\Http\Controllers;

use App\Services\StripeBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeBillingService $stripeBillingService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $this->stripeBillingService->handleWebhook(
            $request->getContent(),
            (string) $request->header('Stripe-Signature', ''),
        );

        return response()->json([
            'received' => true,
        ]);
    }
}