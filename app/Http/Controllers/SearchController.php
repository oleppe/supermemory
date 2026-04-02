<?php

namespace App\Http\Controllers;

use App\Exceptions\CogneeApiException;
use App\Http\Requests\SearchRequest;
use App\Services\CogneeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(
        private readonly CogneeService $cogneeService,
    ) {}

    public function store(SearchRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();

        try {
            $results = $this->withCogneeSession($user, $this->cogneeService, function (string $token) use ($validated) {
                return $this->cogneeService->search(
                    $token,
                    $validated['query'],
                    $validated['dataset_id'] ?? null,
                    $validated['dataset_name'] ?? null,
                    $validated['search_type'] ?? 'GRAPH_COMPLETION',
                    $validated['top_k'] ?? 10,
                    $validated['only_context'] ?? false,
                    $validated['system_prompt'] ?? null,
                    $validated['node_name'] ?? [],
                );
            });
        } catch (CogneeApiException $exception) {
            if ($this->isEmptyKnowledgeGraphError($exception)) {
                throw CogneeApiException::searchRequiresCognify(
                    $validated['dataset_id'] ?? null,
                    $validated['dataset_name'] ?? null,
                );
            }

            throw $exception;
        }

        return response()->json([
            'results' => $results,
            'meta' => [
                'search_type' => $validated['search_type'] ?? 'GRAPH_COMPLETION',
                'top_k' => $validated['top_k'] ?? 10,
            ],
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $history = $this->withCogneeSession($user, $this->cogneeService, function (string $token) {
            return $this->cogneeService->getSearchHistory($token);
        });

        return response()->json([
            'data' => $history,
        ]);
    }

    private function isEmptyKnowledgeGraphError(CogneeApiException $exception): bool
    {
        if ($exception->statusCode !== 404) {
            return false;
        }

        $detail = is_string($exception->cogneeDetail)
            ? $exception->cogneeDetail
            : json_encode($exception->cogneeDetail);

        if (! is_string($detail)) {
            return false;
        }

        $normalized = strtolower($detail);

        return str_contains($normalized, 'knowledge graph is empty')
            || str_contains($normalized, 'nodataerror')
            || str_contains($normalized, 'no data found in the system')
            || str_contains($normalized, 'run cognify');
    }
}
