<?php

namespace App\Exceptions;

use Exception;

class SupermemoryApiException extends Exception
{
    public function __construct(
        public readonly int $statusCode,
        public readonly mixed $detail = null,
        string $message = 'Supermemory API error',
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
                'code' => 'SUPERMEMORY_NOT_CONFIGURED',
                'hint' => 'Set SUPERMEMORY_API_KEY in the environment before using this endpoint.',
            ],
            message: 'Supermemory API is not configured',
        );
    }
}
