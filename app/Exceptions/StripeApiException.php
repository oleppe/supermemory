<?php

namespace App\Exceptions;

use App\Models\Plan;
use Exception;
use Throwable;

class StripeApiException extends Exception
{
    public function __construct(
        public readonly int $statusCode,
        public readonly mixed $detail = null,
        string $message = 'Stripe API error',
    ) {
        parent::__construct($message, $statusCode);
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => $this->message,
            'detail' => $this->detail,
        ], $this->statusCode);
    }

    public static function notConfigured(): self
    {
        return new self(
            statusCode: 503,
            detail: [
                'code' => 'STRIPE_NOT_CONFIGURED',
                'hint' => 'Set STRIPE_SECRET_KEY and STRIPE_WEBHOOK_SECRET before using billing endpoints.',
            ],
            message: 'Stripe billing is not configured',
        );
    }

    public static function redirectUrlsMissing(): self
    {
        return new self(
            statusCode: 422,
            detail: [
                'code' => 'STRIPE_REDIRECT_URLS_REQUIRED',
            ],
            message: 'A success URL and cancel URL are required to create a checkout session.',
        );
    }

    public static function billingPortalReturnUrlMissing(): self
    {
        return new self(
            statusCode: 422,
            detail: [
                'code' => 'STRIPE_BILLING_PORTAL_RETURN_URL_REQUIRED',
            ],
            message: 'A return URL is required to create a billing portal session.',
        );
    }

    public static function planRequiresStripePrice(Plan $plan): self
    {
        return new self(
            statusCode: 422,
            detail: [
                'code' => 'PLAN_NOT_READY_FOR_CHECKOUT',
                'plan_id' => $plan->id,
                'plan_code' => $plan->code,
            ],
            message: 'This plan is not configured with a Stripe recurring price yet.',
        );
    }

    public static function freePlanDoesNotRequireCheckout(): self
    {
        return new self(
            statusCode: 422,
            detail: [
                'code' => 'FREE_PLAN_NO_CHECKOUT',
            ],
            message: 'The free plan does not require Stripe Checkout.',
        );
    }

    public static function alreadyOnPlan(): self
    {
        return new self(
            statusCode: 422,
            detail: [
                'code' => 'ALREADY_ON_PLAN',
            ],
            message: 'The user is already on that plan.',
        );
    }

    public static function usePortalForExistingStripeSubscription(): self
    {
        return new self(
            statusCode: 409,
            detail: [
                'code' => 'USE_BILLING_PORTAL',
            ],
            message: 'This account already has a Stripe-managed subscription. Use the billing portal to change plans.',
        );
    }

    public static function paymentSheetUnavailable(): self
    {
        return new self(
            statusCode: 502,
            detail: [
                'code' => 'STRIPE_PAYMENT_SHEET_UNAVAILABLE',
            ],
            message: 'Unable to prepare the Stripe PaymentSheet payload for this subscription.',
        );
    }

    public static function billingPortalUnavailable(): self
    {
        return new self(
            statusCode: 422,
            detail: [
                'code' => 'BILLING_PORTAL_UNAVAILABLE',
            ],
            message: 'No Stripe billing profile exists for this user yet.',
        );
    }

    public static function managedSubscriptionRequired(): self
    {
        return new self(
            statusCode: 422,
            detail: [
                'code' => 'STRIPE_SUBSCRIPTION_REQUIRED',
            ],
            message: 'This action requires a Stripe-managed subscription.',
        );
    }

    public static function invalidWebhookSignature(): self
    {
        return new self(
            statusCode: 400,
            detail: [
                'code' => 'STRIPE_INVALID_SIGNATURE',
            ],
            message: 'Stripe webhook signature verification failed.',
        );
    }

    public static function syncContextMissing(): self
    {
        return new self(
            statusCode: 422,
            detail: [
                'code' => 'STRIPE_SYNC_CONTEXT_MISSING',
            ],
            message: 'Unable to match the Stripe subscription to a local user and plan.',
        );
    }

    public static function fromThrowable(Throwable $exception, string $message = 'Stripe API request failed'): self
    {
        return new self(
            statusCode: 502,
            detail: [
                'code' => 'STRIPE_REQUEST_FAILED',
                'exception' => $exception->getMessage(),
            ],
            message: $message,
        );
    }
}