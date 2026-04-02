<?php

namespace App\Exceptions;

use Exception;

class CogneeApiException extends Exception
{
    public function __construct(
        public readonly int $statusCode,
        public readonly mixed $cogneeDetail = null,
        string $message = 'Cognee API error',
    ) {
        parent::__construct($message, $statusCode);
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => $this->message,
            'detail' => $this->cogneeDetail,
        ], $this->statusCode);
    }

    public static function sessionExpired(): self
    {
        return new self(
            statusCode: 401,
            cogneeDetail: 'COGNEE_SESSION_EXPIRED',
            message: 'Cognee session expired',
        );
    }

    public static function sessionRecoveryUnavailable(): self
    {
        return new self(
            statusCode: 401,
            cogneeDetail: [
                'code' => 'COGNEE_SESSION_RECOVERY_UNAVAILABLE',
                'action' => 'session_restore',
                'hint' => 'Call /api/session/restore while the Sanctum session is still valid.',
            ],
            message: 'Cognee session could not be restored automatically',
        );
    }

    public static function searchRequiresCognify(?string $datasetId = null, ?string $datasetName = null): self
    {
        return new self(
            statusCode: 409,
            cogneeDetail: array_filter([
                'code' => 'SEARCH_REQUIRES_COGNIFY',
                'action' => 'run_cognify',
                'dataset_id' => $datasetId,
                'dataset_name' => $datasetName,
                'hint' => 'Dataset has data, but the knowledge graph is empty. Run cognify before searching.',
            ], static fn (mixed $value) => $value !== null),
            message: 'Search requires cognify to be run first',
        );
    }
}
