<?php

namespace App\Exceptions;

use Exception;

class GeminiApiException extends Exception
{
    public function __construct(
        public readonly int $statusCode,
        public readonly mixed $detail = null,
        string $message = 'Gemini API error',
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
                'code' => 'GEMINI_NOT_CONFIGURED',
                'hint' => 'Set GEMINI_API_KEY in the environment before using this endpoint.',
            ],
            message: 'Gemini API is not configured',
        );
    }

    public static function malformedResponse(string $message = 'Gemini API returned malformed JSON', mixed $detail = null): self
    {
        return new self(
            statusCode: 502,
            detail: $detail,
            message: $message,
        );
    }
}
