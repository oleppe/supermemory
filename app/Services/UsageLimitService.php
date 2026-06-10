<?php

namespace App\Services;

use App\Exceptions\UsageLimitExceededException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageCounter;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class UsageLimitService
{
    public const METRIC_FILES = 'files';

    public const METRIC_AI_QUESTIONS = 'ai_questions';

    public const PERIOD_BILLING_CYCLE = 'billing_cycle';

    public const PERIOD_DAILY = 'daily';

    public function __construct(
        private readonly PdfPageCounter $pdfPageCounter,
        private readonly SubscriptionService $subscriptionService,
    ) {}

    public function ensureFileUploadsAllowed(User $user, array $files): void
    {
        $normalizedFiles = array_values(array_filter(
            $files,
            fn (mixed $file): bool => $file instanceof UploadedFile,
        ));

        if ($normalizedFiles === []) {
            return;
        }

        $subscription = $this->subscriptionService->resolveActiveSubscription($user);

        $this->assertWithinLimit($user, $subscription, self::METRIC_FILES, count($normalizedFiles));
        $this->assertPdfPageLimit($normalizedFiles, $subscription->plan);
    }

    public function consumeFileUploads(User $user, int $amount = 1): void
    {
        $this->consume($user, self::METRIC_FILES, $amount);
    }

    public function ensureAiQuestionAllowed(User $user, int $amount = 1): void
    {
        $subscription = $this->subscriptionService->resolveActiveSubscription($user);

        $this->assertWithinLimit($user, $subscription, self::METRIC_AI_QUESTIONS, $amount);
    }

    public function consumeAiQuestions(User $user, int $amount = 1): void
    {
        $this->consume($user, self::METRIC_AI_QUESTIONS, $amount);
    }

    public function snapshot(User $user): array
    {
        $subscription = $this->subscriptionService->resolveActiveSubscription($user);

        return [
            'files' => $this->metricSnapshot($user, $subscription, self::METRIC_FILES),
            'ai_questions' => $this->metricSnapshot($user, $subscription, self::METRIC_AI_QUESTIONS),
        ];
    }

    private function consume(User $user, string $metric, int $amount): void
    {
        if ($amount < 1) {
            return;
        }

        DB::transaction(function () use ($amount, $metric, $user): void {
            $subscription = $this->subscriptionService->resolveActiveSubscription($user, true);
            $limits = $this->limitsForMetric($subscription, $metric);
            $now = CarbonImmutable::now();

            foreach ($limits as $limit) {
                if (! is_int($limit['limit'])) {
                    continue;
                }

                $counter = $this->counterQuery($user, $metric, $limit['period'], $limit['start'])
                    ->lockForUpdate()
                    ->first();

                if (! $counter instanceof UsageCounter) {
                    $counter = new UsageCounter([
                        'user_id' => $user->id,
                        'metric' => $metric,
                        'period' => $limit['period'],
                        'period_start' => $limit['start'],
                        'period_end' => $limit['end'],
                        'used' => 0,
                    ]);
                }

                if ($counter->used + $amount > $limit['limit']) {
                    throw UsageLimitExceededException::forLimit(
                        $metric,
                        $limit['period'],
                        $limit['limit'],
                        $counter->used,
                        $amount,
                        $limit['end'],
                        $subscription->plan,
                    );
                }

                $counter->period_end = $limit['end'];
                $counter->used += $amount;
                $counter->last_used_at = $now;
                $counter->save();
            }
        });
    }

    private function assertWithinLimit(User $user, Subscription $subscription, string $metric, int $amount): void
    {
        foreach ($this->limitsForMetric($subscription, $metric) as $limit) {
            if (! is_int($limit['limit'])) {
                continue;
            }

            $used = (int) $this->counterQuery($user, $metric, $limit['period'], $limit['start'])
                ->value('used');

            if ($used + $amount > $limit['limit']) {
                throw UsageLimitExceededException::forLimit(
                    $metric,
                    $limit['period'],
                    $limit['limit'],
                    $used,
                    $amount,
                    $limit['end'],
                    $subscription->plan,
                );
            }
        }
    }

    private function metricSnapshot(User $user, Subscription $subscription, string $metric): array
    {
        $snapshot = [];

        foreach ($this->limitsForMetric($subscription, $metric) as $limit) {
            $used = (int) $this->counterQuery($user, $metric, $limit['period'], $limit['start'])
                ->value('used');

            $snapshot[$limit['period']] = [
                'limit' => $limit['limit'],
                'used' => $used,
                'remaining' => is_int($limit['limit']) ? max($limit['limit'] - $used, 0) : null,
                'period_start' => $limit['start']->toIso8601String(),
                'period_end' => $limit['end']->toIso8601String(),
            ];
        }

        $billing = $snapshot[self::PERIOD_BILLING_CYCLE] ?? null;

        return [
            'metric' => $metric,
            'limit' => $billing['limit'] ?? null,
            'used' => $billing['used'] ?? 0,
            'remaining' => $billing['remaining'] ?? null,
            'billing_cycle' => $snapshot[self::PERIOD_BILLING_CYCLE] ?? null,
            'daily' => $snapshot[self::PERIOD_DAILY] ?? null,
        ];
    }

    private function limitsForMetric(Subscription $subscription, string $metric): array
    {
        $plan = $subscription->plan;

        if (! $plan instanceof Plan) {
            throw new RuntimeException('Subscription is missing its plan.');
        }

        $billingStart = CarbonImmutable::instance($subscription->current_period_start);
        $billingEnd = CarbonImmutable::instance($subscription->current_period_end);
        $todayStart = CarbonImmutable::now()->startOfDay();

        return match ($metric) {
            self::METRIC_FILES => [
                [
                    'period' => self::PERIOD_BILLING_CYCLE,
                    'limit' => $plan->monthly_file_limit,
                    'start' => $billingStart,
                    'end' => $billingEnd,
                ],
                [
                    'period' => self::PERIOD_DAILY,
                    'limit' => $plan->daily_file_limit,
                    'start' => $todayStart,
                    'end' => $todayStart->addDay(),
                ],
            ],
            self::METRIC_AI_QUESTIONS => [
                [
                    'period' => self::PERIOD_BILLING_CYCLE,
                    'limit' => $plan->monthly_question_limit,
                    'start' => $billingStart,
                    'end' => $billingEnd,
                ],
                [
                    'period' => self::PERIOD_DAILY,
                    'limit' => $plan->daily_question_limit,
                    'start' => $todayStart,
                    'end' => $todayStart->addDay(),
                ],
            ],
            default => throw new RuntimeException(sprintf('Unsupported usage metric [%s].', $metric)),
        };
    }

    private function assertPdfPageLimit(array $files, Plan $plan): void
    {
        foreach ($files as $index => $file) {
            if (! $this->isPdf($file)) {
                continue;
            }

            $pageCount = $this->pdfPageCounter->countPages($file);

            if ($pageCount > $plan->max_pdf_pages) {
                throw ValidationException::withMessages([
                    'files.'.$index => [sprintf('PDF uploads are limited to %d pages on the %s plan.', $plan->max_pdf_pages, $plan->name)],
                ]);
            }
        }
    }

    private function counterQuery(User $user, string $metric, string $period, CarbonImmutable $periodStart)
    {
        return UsageCounter::query()
            ->where('user_id', $user->id)
            ->where('metric', $metric)
            ->where('period', $period)
            ->where('period_start', $periodStart);
    }

    private function isPdf(UploadedFile $file): bool
    {
        $mimeTypes = array_filter([
            $file->getMimeType(),
            $file->getClientMimeType(),
        ]);

        if (in_array('application/pdf', $mimeTypes, true)) {
            return true;
        }

        return strtolower($file->getClientOriginalExtension()) === 'pdf';
    }
}