<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateDatasetRequest;
use App\Services\CogneeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DatasetController extends Controller
{
    public function __construct(
        private readonly CogneeService $cogneeService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $datasets = $this->withCogneeSession($user, $this->cogneeService, function (string $token) {
            return $this->cogneeService->getDatasets($token);
        });

        return response()->json([
            'data' => $datasets,
        ]);
    }

    public function store(CreateDatasetRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $dataset = $this->withCogneeSession($user, $this->cogneeService, function (string $token) use ($request) {
            return $this->cogneeService->createDataset(
                $token,
                $request->validated('name'),
            );
        });

        return response()->json([
            'data' => $dataset,
        ], 201);
    }

    public function status(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dataset_ids' => ['nullable', 'array'],
            'dataset_ids.*' => ['uuid'],
        ]);

        $user = $this->authenticatedUser($request);
        $statuses = $this->withCogneeSession($user, $this->cogneeService, function (string $token) use ($validated) {
            return $this->cogneeService->getDatasetStatus(
                $token,
                $validated['dataset_ids'] ?? [],
            );
        });

        $mappedStatuses = [];

        foreach ($statuses as $datasetId => $status) {
            $mappedStatuses[$datasetId] = [
                'upstream' => $status,
                'state' => match ($status) {
                    'DATASET_PROCESSING_INITIATED' => 'queued',
                    'DATASET_PROCESSING_STARTED' => 'processing',
                    'DATASET_PROCESSING_COMPLETED' => 'completed',
                    'DATASET_PROCESSING_ERRORED' => 'failed',
                    default => 'unknown',
                },
            ];
        }

        return response()->json([
            'data' => $mappedStatuses,
        ]);
    }
}
