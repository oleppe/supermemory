<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemifyRequest;
use App\Services\CogneeService;
use Illuminate\Http\JsonResponse;

class MemifyController extends Controller
{
    public function __construct(
        private readonly CogneeService $cogneeService,
    ) {}

    public function store(MemifyRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();

        $result = $this->withCogneeSession($user, $this->cogneeService, function (string $token) use ($validated) {
            return $this->cogneeService->memify(
                $token,
                $validated['dataset_id'] ?? null,
                $validated['dataset_name'] ?? null,
                $validated['extraction_tasks'] ?? [],
                $validated['enrichment_tasks'] ?? [],
                $validated['data'] ?? null,
                $validated['node_name'] ?? [],
                $validated['run_in_background'] ?? true,
            );
        });

        return response()->json([
            'result' => $result,
        ]);
    }
}