<?php

namespace App\Services;

use App\Exceptions\StripeApiException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Stripe\EphemeralKey;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeBillingService
{
    private ?StripeClient $client = null;

    public function __construct(
        private readonly SubscriptionService $subscriptionService,
    ) {}

    public function isConfigured(): bool
    {
        return filled(config('services.stripe.secret_key'));
    }

    public function canCheckout(Plan $plan): bool
    {
        return $this->isConfigured()
            && $plan->price_cents > 0
            && filled($plan->stripe_price_id)
            && $plan->is_active;
    }

    public function canOpenPortal(User $user): bool
    {
        return $this->isConfigured() && filled($user->stripe_customer_id);
    }

    public function createPaymentSheet(User $user, Plan $plan): array
    {
        if (! $this->isConfigured()) {
            throw StripeApiException::notConfigured();
        }

        if ($plan->price_cents < 1) {
            throw StripeApiException::freePlanDoesNotRequireCheckout();
        }

        if (! filled($plan->stripe_price_id)) {
            throw StripeApiException::planRequiresStripePrice($plan);
        }

        $activeSubscription = $this->subscriptionService->resolveActiveSubscription($user);

        if ($activeSubscription->plan_id === $plan->id) {
            throw StripeApiException::alreadyOnPlan();
        }

        if ($activeSubscription->provider === 'stripe') {
            throw StripeApiException::usePortalForExistingStripeSubscription();
        }

        $customerId = $this->ensureCustomer($user);

        try {
            $ephemeralKey = EphemeralKey::create(
                ['customer' => $customerId],
                [
                    'api_key' => (string) config('services.stripe.secret_key'),
                    'stripe_version' => $this->ephemeralKeyApiVersion(),
                ],
            );
        } catch (ApiErrorException $exception) {
            throw StripeApiException::fromThrowable($exception, 'Unable to create a Stripe ephemeral key for mobile billing.');
        }

        try {
            $subscription = $this->client()->subscriptions->create([
                'customer' => $customerId,
                'items' => [
                    [
                        'price' => $plan->stripe_price_id,
                    ],
                ],
                'payment_behavior' => 'default_incomplete',
                'payment_settings' => [
                    'save_default_payment_method' => 'on_subscription',
                    'payment_method_types' => ['card'],
                ],
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'plan_id' => (string) $plan->id,
                ],
                'expand' => ['latest_invoice.confirmation_secret', 'latest_invoice.payment_intent'],
            ]);
        } catch (ApiErrorException $exception) {
            throw StripeApiException::fromThrowable($exception, 'Unable to create a Stripe subscription payment intent.');
        }

        $payload = $subscription->toArray();
        $paymentIntentClientSecret = data_get($payload, 'latest_invoice.confirmation_secret.client_secret')
            ?? data_get($payload, 'latest_invoice.payment_intent.client_secret');

        if (! is_string($paymentIntentClientSecret) || $paymentIntentClientSecret === '') {
            throw StripeApiException::paymentSheetUnavailable();
        }

        $localSubscription = $this->syncStripeSubscription($subscription);

        return [
            'customer_id' => $customerId,
            'ephemeral_key_secret' => $ephemeralKey->secret,
            'payment_intent_client_secret' => $paymentIntentClientSecret,
            'subscription_id' => $subscription->id,
            'subscription' => $this->subscriptionService->serializeSubscription($localSubscription),
        ];
    }

    public function createBillingPortalSession(User $user, ?string $returnUrl = null): array
    {
        if (! $this->isConfigured()) {
            throw StripeApiException::notConfigured();
        }

        if (! filled($user->stripe_customer_id)) {
            throw StripeApiException::billingPortalUnavailable();
        }

        $returnUrl = $returnUrl ?: config('services.stripe.billing_portal_return_url');

        if (! is_string($returnUrl) || $returnUrl === '') {
            throw StripeApiException::billingPortalReturnUrlMissing();
        }

        try {
            $session = $this->client()->billingPortal->sessions->create([
                'customer' => $user->stripe_customer_id,
                'return_url' => $returnUrl,
            ]);
        } catch (ApiErrorException $exception) {
            throw StripeApiException::fromThrowable($exception, 'Unable to create a Stripe billing portal session.');
        }

        return [
            'url' => $session->url,
        ];
    }

    public function refreshSubscription(User $user, string $stripeSubscriptionId): Subscription
    {
        if (! $this->isConfigured()) {
            throw StripeApiException::notConfigured();
        }

        $subscription = Subscription::query()
            ->where('user_id', $user->id)
            ->where('provider', 'stripe')
            ->where('stripe_subscription_id', $stripeSubscriptionId)
            ->first();

        if (! $subscription instanceof Subscription) {
            throw StripeApiException::managedSubscriptionRequired();
        }

        try {
            $stripeSubscription = $this->client()->subscriptions->retrieve($stripeSubscriptionId);
        } catch (ApiErrorException $exception) {
            throw StripeApiException::fromThrowable($exception, 'Unable to refresh the Stripe subscription status.');
        }

        return $this->syncStripeSubscription($stripeSubscription, $subscription->stripe_checkout_session_id);
    }

    public function cancelSubscription(User $user, Subscription $subscription, bool $immediately = false): Subscription
    {
        if (! $this->isConfigured()) {
            throw StripeApiException::notConfigured();
        }

        if ($subscription->user_id !== $user->id || $subscription->provider !== 'stripe' || ! filled($subscription->stripe_subscription_id)) {
            throw StripeApiException::managedSubscriptionRequired();
        }

        try {
            $stripeSubscription = $immediately
                ? $this->client()->subscriptions->cancel($subscription->stripe_subscription_id)
                : $this->client()->subscriptions->update($subscription->stripe_subscription_id, [
                    'cancel_at_period_end' => true,
                ]);
        } catch (ApiErrorException $exception) {
            throw StripeApiException::fromThrowable($exception, 'Unable to cancel the Stripe subscription.');
        }

        return $this->syncStripeSubscription($stripeSubscription, $subscription->stripe_checkout_session_id);
    }

    public function resumeSubscription(User $user, Subscription $subscription): Subscription
    {
        if (! $this->isConfigured()) {
            throw StripeApiException::notConfigured();
        }

        if ($subscription->user_id !== $user->id || $subscription->provider !== 'stripe' || ! filled($subscription->stripe_subscription_id)) {
            throw StripeApiException::managedSubscriptionRequired();
        }

        try {
            $stripeSubscription = $this->client()->subscriptions->update($subscription->stripe_subscription_id, [
                'cancel_at_period_end' => false,
            ]);
        } catch (ApiErrorException $exception) {
            throw StripeApiException::fromThrowable($exception, 'Unable to resume the Stripe subscription.');
        }

        return $this->syncStripeSubscription($stripeSubscription, $subscription->stripe_checkout_session_id);
    }

    public function handleWebhook(string $payload, string $signature): void
    {
        if (! $this->isConfigured() || ! filled(config('services.stripe.webhook_secret'))) {
            throw StripeApiException::notConfigured();
        }

        try {
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                (string) config('services.stripe.webhook_secret'),
            );
        } catch (UnexpectedValueException|SignatureVerificationException) {
            throw StripeApiException::invalidWebhookSignature();
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                /** @var StripeCheckoutSession $session */
                $session = $event->data->object;
                $sessionData = $session->toArray();

                if (($sessionData['mode'] ?? null) === 'subscription' && filled($sessionData['subscription'] ?? null)) {
                    $this->syncSubscriptionByStripeId(
                        (string) $sessionData['subscription'],
                        'Unable to retrieve the completed Stripe subscription.',
                        (string) ($sessionData['id'] ?? ''),
                    );
                }
                break;

            case 'customer.subscription.created':
            case 'customer.subscription.updated':
            case 'customer.subscription.deleted':
                /** @var StripeSubscription $subscription */
                $subscription = $event->data->object;
                $this->syncStripeSubscription($subscription);
                break;

            case 'invoice.paid':
            case 'invoice.payment_succeeded':
            case 'invoice.payment_failed':
            case 'invoice.payment_action_required':
                $invoice = $event->data->object->toArray();
                $this->syncSubscriptionFromInvoicePayload(
                    $invoice,
                    'Unable to retrieve the Stripe subscription from the invoice event.',
                );
                break;

            case 'payment_intent.succeeded':
            case 'payment_intent.processing':
            case 'payment_intent.requires_action':
            case 'payment_intent.payment_failed':
            case 'payment_intent.canceled':
                $paymentIntent = $event->data->object->toArray();
                $this->syncSubscriptionFromPaymentIntentPayload(
                    $paymentIntent,
                    'Unable to retrieve the Stripe subscription from the payment intent event.',
                );
                break;
        }
    }

    private function ensureCustomer(User $user): string
    {
        if (filled($user->stripe_customer_id)) {
            return (string) $user->stripe_customer_id;
        }

        try {
            $customer = $this->client()->customers->create([
                'email' => $user->email,
                'name' => $user->name,
                'metadata' => [
                    'user_id' => (string) $user->id,
                ],
            ]);
        } catch (ApiErrorException $exception) {
            throw StripeApiException::fromThrowable($exception, 'Unable to create a Stripe customer profile.');
        }

        $user->forceFill([
            'stripe_customer_id' => $customer->id,
        ])->save();

        return $customer->id;
    }

    private function syncStripeSubscription(StripeSubscription $stripeSubscription, ?string $checkoutSessionId = null): Subscription
    {
        $payload = $stripeSubscription->toArray();
        $user = $this->resolveUserFromStripePayload($payload);
        $plan = $this->resolvePlanFromStripePayload($payload);

        if (! $user instanceof User || ! $plan instanceof Plan) {
            throw StripeApiException::syncContextMissing();
        }

        if (filled($payload['customer'] ?? null) && $user->stripe_customer_id !== $payload['customer']) {
            $user->forceFill([
                'stripe_customer_id' => (string) $payload['customer'],
            ])->save();
        }

        $status = (string) ($payload['status'] ?? 'incomplete');
        $periodStart = $this->timestampToCarbon($payload['current_period_start'] ?? null) ?? CarbonImmutable::now()->startOfDay();
        $periodEnd = $this->timestampToCarbon($payload['current_period_end'] ?? null) ?? $periodStart->addMonths(max($plan->billing_interval_months, 1));
        $cancelledAt = $this->timestampToCarbon($payload['canceled_at'] ?? null);
        $endsAt = $this->timestampToCarbon($payload['cancel_at'] ?? null)
            ?? $this->timestampToCarbon($payload['ended_at'] ?? null)
            ?? ((bool) ($payload['cancel_at_period_end'] ?? false) ? $periodEnd : null);

        $attributes = [
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'provider' => 'stripe',
            'status' => $status,
            'current_period_start' => $periodStart,
            'current_period_end' => $periodEnd,
            'cancelled_at' => $cancelledAt,
            'ends_at' => $endsAt,
            'cancel_at_period_end' => (bool) ($payload['cancel_at_period_end'] ?? false),
            'assignment_note' => 'Managed by Stripe billing.',
        ];

        if ($checkoutSessionId !== null && $checkoutSessionId !== '') {
            $attributes['stripe_checkout_session_id'] = $checkoutSessionId;
        }

        $subscription = DB::transaction(function () use ($attributes, $payload, $status, $user): Subscription {
            $existing = Subscription::query()
                ->where('stripe_subscription_id', (string) ($payload['id'] ?? ''))
                ->first();

            if ($this->shouldIgnoreStatusRegression($existing, $status)) {
                if (($attributes['stripe_checkout_session_id'] ?? null) !== null && blank($existing->stripe_checkout_session_id)) {
                    $existing->forceFill([
                        'stripe_checkout_session_id' => $attributes['stripe_checkout_session_id'],
                    ])->save();
                }

                return $existing;
            }

            if (in_array($status, Subscription::ENTITLING_STATUSES, true)) {
                Subscription::query()
                    ->where('user_id', $user->id)
                    ->whereIn('status', Subscription::ENTITLING_STATUSES)
                    ->when($existing instanceof Subscription, fn ($query) => $query->whereKeyNot($existing->id))
                    ->update([
                        'status' => 'replaced',
                        'cancelled_at' => now(),
                        'ends_at' => now(),
                        'cancel_at_period_end' => false,
                        'updated_at' => now(),
                    ]);
            }

            return Subscription::query()->updateOrCreate(
                ['stripe_subscription_id' => (string) ($payload['id'] ?? '')],
                $attributes,
            );
        });

        $user->unsetRelation('activeSubscription');

        return $subscription->load('plan');
    }

    private function shouldIgnoreStatusRegression(?Subscription $existing, string $incomingStatus): bool
    {
        if (! $existing instanceof Subscription) {
            return false;
        }

        return in_array($existing->status, Subscription::ENTITLING_STATUSES, true)
            && in_array($incomingStatus, ['incomplete', 'incomplete_expired'], true);
    }

    private function syncSubscriptionFromInvoicePayload(array $invoice, string $errorMessage): void
    {
        $subscriptionId = $this->resolveSubscriptionIdFromInvoicePayload($invoice);

        if ($subscriptionId === null) {
            return;
        }

        $this->syncSubscriptionByStripeId($subscriptionId, $errorMessage);
    }

    private function resolveSubscriptionIdFromInvoicePayload(array $invoice): ?string
    {
        $candidatePaths = [
            'subscription',
            'subscription_details.subscription',
            'parent.subscription_details.subscription',
            'lines.data.0.subscription',
            'lines.data.0.parent.subscription_details.subscription',
            'lines.data.0.parent.subscription_item_details.subscription',
        ];

        foreach ($candidatePaths as $path) {
            $subscriptionId = data_get($invoice, $path);

            if (is_string($subscriptionId) && $subscriptionId !== '') {
                return $subscriptionId;
            }
        }

        foreach (data_get($invoice, 'lines.data', []) as $lineItem) {
            if (! is_array($lineItem)) {
                continue;
            }

            foreach ([
                'subscription',
                'parent.subscription_details.subscription',
                'parent.subscription_item_details.subscription',
            ] as $path) {
                $subscriptionId = data_get($lineItem, $path);

                if (is_string($subscriptionId) && $subscriptionId !== '') {
                    return $subscriptionId;
                }
            }
        }

        return null;
    }

    private function syncSubscriptionFromPaymentIntentPayload(array $paymentIntent, string $errorMessage): void
    {
        $invoiceId = $paymentIntent['invoice'] ?? null;

        if (! is_string($invoiceId) || $invoiceId === '') {
            return;
        }

        try {
            $invoice = $this->client()->invoices->retrieve($invoiceId)->toArray();
        } catch (ApiErrorException $exception) {
            throw StripeApiException::fromThrowable($exception, $errorMessage);
        }

        $this->syncSubscriptionFromInvoicePayload($invoice, $errorMessage);
    }

    private function syncSubscriptionByStripeId(string $subscriptionId, string $errorMessage, ?string $checkoutSessionId = null): void
    {
        try {
            $subscription = $this->client()->subscriptions->retrieve($subscriptionId);
        } catch (ApiErrorException $exception) {
            throw StripeApiException::fromThrowable($exception, $errorMessage);
        }

        $this->syncStripeSubscription($subscription, $checkoutSessionId);
    }

    private function resolveUserFromStripePayload(array $payload): ?User
    {
        $userId = data_get($payload, 'metadata.user_id');

        if (is_numeric($userId)) {
            $user = User::query()->find((int) $userId);

            if ($user instanceof User) {
                return $user;
            }
        }

        $customerId = $payload['customer'] ?? null;

        if (is_string($customerId) && $customerId !== '') {
            return User::query()->where('stripe_customer_id', $customerId)->first();
        }

        return null;
    }

    private function resolvePlanFromStripePayload(array $payload): ?Plan
    {
        $planId = data_get($payload, 'metadata.plan_id');

        if (is_numeric($planId)) {
            $plan = Plan::query()->find((int) $planId);

            if ($plan instanceof Plan) {
                return $plan;
            }
        }

        $priceId = data_get($payload, 'items.data.0.price.id');

        if (is_string($priceId) && $priceId !== '') {
            return Plan::query()->where('stripe_price_id', $priceId)->first();
        }

        return null;
    }

    private function timestampToCarbon(mixed $timestamp): ?CarbonImmutable
    {
        if (! is_numeric($timestamp)) {
            return null;
        }

        return CarbonImmutable::createFromTimestampUTC((int) $timestamp);
    }

    private function client(): StripeClient
    {
        if ($this->client instanceof StripeClient) {
            return $this->client;
        }

        if (! $this->isConfigured()) {
            throw StripeApiException::notConfigured();
        }

        $this->client = new StripeClient((string) config('services.stripe.secret_key'));

        return $this->client;
    }

    private function ephemeralKeyApiVersion(): string
    {
        $version = config('services.stripe.ephemeral_key_api_version');

        return is_string($version) && $version !== ''
            ? $version
            : '2024-11-20.acacia';
    }
}
