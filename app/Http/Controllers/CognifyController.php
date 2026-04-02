<?php

namespace App\Http\Controllers;

use App\Http\Requests\CognifyRequest;
use App\Services\CogneeService;
use Illuminate\Http\JsonResponse;

class CognifyController extends Controller
{
    public function __construct(
        private readonly CogneeService $cogneeService,
    ) {}

    public function store(CognifyRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();
        [$dataset, $result] = $this->withCogneeSession($user, $this->cogneeService, function (string $token) use ($validated) {
            $dataset = null;
            if (($validated['dataset_name'] ?? null) !== null) {
                $dataset = $this->cogneeService->createDataset($token, $validated['dataset_name']);
            }

            $result = $this->cogneeService->cognify(
                $token,
                $validated['dataset_id'] ?? ($dataset['id'] ?? null),
                $validated['dataset_name'] ?? ($dataset['name'] ?? null),
                $validated['run_in_background'] ?? true,
                $validated['custom_prompt'] ?? null,
                $validated['ontology_key'] ?? [],
            );

            return [$dataset, $result];
        });

        return response()->json([
            'dataset' => $dataset,
            'result' => $result,
        ]);
    }
}
