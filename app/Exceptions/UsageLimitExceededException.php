<?php

namespace App\Exceptions;

use App\Models\Plan;
use Carbon\CarbonInterface;
use Exception;

class UsageLimitExceededException extends Exception
{
    public function __construct(
        public readonly int $statusCode,
        public readonly mixed $detail = null,
        string $message = 'Usage limit exceeded',
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

    public static function forLimit(
        string $metric,
        string $period,
        int $limit,
        int $used,
        int $requested,
        CarbonInterface $resetsAt,
        Plan $plan,
    ): self {
        $remaining = max($limit - $used, 0);

        return new self(
            statusCode: 429,
            detail: [
                'code' => 'USAGE_LIMIT_EXCEEDED',
                'metric' => $metric,
                'period' => $period,
                'limit' => $limit,
                'used' => $used,
                'requested' => $requested,
                'remaining' => $remaining,
                'reset_at' => $resetsAt->toIso8601String(),
                'plan' => [
                    'code' => $plan->code,
                    'name' => $plan->name,
                ],
            ],
            message: sprintf('You have reached your %s %s limit.', str_replace('_', ' ', $period), str_replace('_', ' ', $metric)),
        );
    }
}