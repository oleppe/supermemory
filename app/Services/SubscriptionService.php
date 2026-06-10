<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SubscriptionService
{
    public function defaultPlan(): Plan
    {
        $plan = Plan::query()
            ->where('code', 'free')
            ->where('is_active', true)
            ->first();

        if (! $plan instanceof Plan) {
            throw new RuntimeException('Default free plan is missing from the database.');
        }

        return $plan;
    }

    public function activePlans(): array
    {
        return Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function resolveActiveSubscription(User $user, bool $forceRefresh = false): Subscription
    {
        if ($forceRefresh) {
            $user->unsetRelation('activeSubscription');
        }

        $subscription = $user->activeSubscription()
            ->with('plan')
            ->first();

        if (! $subscription instanceof Subscription) {
            return $this->createDefaultSubscription($user);
        }

        $user->setRelation('activeSubscription', $subscription);

        return $this->rollForwardPeriod($subscription);
    }

    public function createDefaultSubscription(User $user, ?User $assignedBy = null, ?string $note = null): Subscription
    {
        $plan = $this->defaultPlan();
        $existing = $user->subscriptions()
            ->with('plan')
            ->where('plan_id', $plan->id)
            ->where('provider', 'manual')
            ->entitling()
            ->latest('id')
            ->first();

        if ($existing instanceof Subscription) {
            $user->setRelation('activeSubscription', $existing);

            return $this->rollForwardPeriod($existing);
        }

        return $this->assignPlan(
            $user,
            $plan,
            $assignedBy,
            $note ?? 'Assigned default free plan.',
        );
    }

    public function assignPlan(
        User $user,
        Plan $plan,
        ?User $assignedBy = null,
        ?string $note = null,
        ?CarbonImmutable $startsAt = null,
    ): Subscription {
        $periodStart = $startsAt ?? CarbonImmutable::now()->startOfDay();
        $periodEnd = $periodStart->addMonths(max($plan->billing_interval_months, 1));

        /** @var Subscription $subscription */
        $subscription = DB::transaction(function () use ($assignedBy, $note, $periodEnd, $periodStart, $plan, $user): Subscription {
            Subscription::query()
                ->where('user_id', $user->id)
                ->whereIn('status', Subscription::ENTITLING_STATUSES)
                ->update([
                    'status' => 'replaced',
                    'cancelled_at' => $periodStart,
                    'ends_at' => $periodStart,
                    'cancel_at_period_end' => false,
                    'updated_at' => now(),
                ]);

            return Subscription::query()->create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'provider' => 'manual',
                'status' => 'active',
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
                'cancel_at_period_end' => false,
                'assigned_by' => $assignedBy?->id,
                'assignment_note' => $note,
            ]);
        });

        $user->unsetRelation('activeSubscription');

        return $subscription->load('plan');
    }

    public function serializePlan(Plan $plan): array
    {
        return [
            'id' => $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'description' => $plan->description,
            'price_cents' => $plan->price_cents,
            'currency' => $plan->currency,
            'billing_interval_months' => $plan->billing_interval_months,
            'limits' => [
                'monthly_files' => $plan->monthly_file_limit,
                'daily_files' => $plan->daily_file_limit,
                'monthly_questions' => $plan->monthly_question_limit,
                'daily_questions' => $plan->daily_question_limit,
                'max_pdf_pages' => $plan->max_pdf_pages,
            ],
            'is_active' => $plan->is_active,
            'sort_order' => $plan->sort_order,
        ];
    }

    public function serializeSubscription(Subscription $subscription): array
    {
        $subscription->loadMissing('plan');

        return [
            'id' => $subscription->id,
            'provider' => $subscription->provider,
            'status' => $subscription->status,
            'current_period_start' => $subscription->current_period_start?->toIso8601String(),
            'current_period_end' => $subscription->current_period_end?->toIso8601String(),
            'cancelled_at' => $subscription->cancelled_at?->toIso8601String(),
            'ends_at' => $subscription->ends_at?->toIso8601String(),
            'cancel_at_period_end' => $subscription->cancel_at_period_end,
            'assignment_note' => $subscription->assignment_note,
            'plan' => $this->serializePlan($subscription->plan),
        ];
    }

    public function serializeAdminPlan(Plan $plan): array
    {
        return array_merge($this->serializePlan($plan), [
            'stripe_price_id' => $plan->stripe_price_id,
            'active_subscriptions_count' => (int) ($plan->active_subscriptions_count ?? 0),
        ]);
    }

    public function serializeAdminSubscription(Subscription $subscription): array
    {
        $subscription->loadMissing('plan', 'user');

        return array_merge($this->serializeSubscription($subscription), [
            'stripe_subscription_id' => $subscription->stripe_subscription_id,
            'stripe_checkout_session_id' => $subscription->stripe_checkout_session_id,
            'user' => [
                'id' => $subscription->user?->id,
                'name' => $subscription->user?->name,
                'email' => $subscription->user?->email,
            ],
        ]);
    }

    private function rollForwardPeriod(Subscription $subscription): Subscription
    {
        $subscription->loadMissing('plan');
        $plan = $subscription->plan;

        if (! $plan instanceof Plan) {
            throw new RuntimeException('Subscription is missing its plan.');
        }

        if ($subscription->provider !== 'manual') {
            return $subscription->refresh()->load('plan');
        }

        $periodStart = CarbonImmutable::instance($subscription->current_period_start);
        $periodEnd = CarbonImmutable::instance($subscription->current_period_end);
        $now = CarbonImmutable::now();
        $dirty = false;

        while ($periodEnd->lessThanOrEqualTo($now)) {
            $periodStart = $periodEnd;
            $periodEnd = $periodStart->addMonths(max($plan->billing_interval_months, 1));
            $dirty = true;
        }

        if ($dirty) {
            $subscription->forceFill([
                'current_period_start' => $periodStart,
                'current_period_end' => $periodEnd,
            ])->save();
        }

        return $subscription->refresh()->load('plan');
    }
}
