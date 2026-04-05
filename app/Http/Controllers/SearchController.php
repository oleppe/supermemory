<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchRequest;
use App\Services\GeminiService;
use App\Services\SupermemoryService;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function __construct(
        private readonly GeminiService $geminiService,
        private readonly SupermemoryService $supermemoryService,
    ) {}

    public function searchMemories(SearchRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();
        $containerTag = $this->supermemoryContainerTag($user);
        $result = $this->supermemoryService->searchMemories(
            $validated['query'],
            $containerTag,
            $validated['limit'] ?? 10,
            $validated['threshold'] ?? null,
            $validated['rerank'] ?? false,
        );

        $entries = array_values(array_filter(
            $result['results'] ?? [],
            fn (array $entry): bool => $this->isMemoryResult($entry),
        ));

        $results = array_map(
            fn (array $entry): array => $this->normalizeMemoryResult($entry),
            $entries,
        );

        return response()->json([
            'results' => $results,
            'meta' => [
                'search_mode' => 'memories',
                'upstream_search_mode' => 'hybrid',
                'limit' => $validated['limit'] ?? 10,
                'threshold' => $validated['threshold'] ?? null,
                'rerank' => $validated['rerank'] ?? false,
                'container_tag' => $containerTag,
                'total' => count($results),
                'timing' => $result['timing'] ?? null,
            ],
        ]);
    }

    public function searchDocuments(SearchRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();
        $containerTag = $this->supermemoryContainerTag($user);
        $result = $this->supermemoryService->searchDocuments(
            $validated['query'],
            $containerTag,
            $validated['limit'] ?? 10,
            $validated['threshold'] ?? null,
            $validated['rerank'] ?? false,
        );

        $entries = array_values(array_filter(
            $result['results'] ?? [],
            fn (mixed $entry): bool => is_array($entry),
        ));

        $contextSegments = $this->extractContextSegments($entries);
        $hasContext = $contextSegments !== [];
        $conversationHistory = $validated['conversationHistory'] ?? [];

        $answer = $hasContext
            ? $this->geminiService->generateAnswer($validated['query'], $contextSegments, $conversationHistory)
            : $this->fallbackNoContextAnswer();

        return response()->json([
            'answer' => $answer,
            'meta' => [
                'search_mode' => 'documents',
                'response_mode' => 'answer_only',
                'upstream_search_mode' => 'hybrid',
                'limit' => $validated['limit'] ?? 10,
                'threshold' => $validated['threshold'] ?? null,
                'rerank' => $validated['rerank'] ?? false,
                'container_tag' => $containerTag,
                'model' => $this->geminiService->model(),
                'context_items' => count($contextSegments),
                'conversation_history_items' => count($conversationHistory),
                'no_context' => ! $hasContext,
                'timing' => $result['timing'] ?? null,
            ],
        ]);
    }

    private function normalizeMemoryResult(array $entry): array
    {
        return [
            'id' => $entry['id'] ?? null,
            'content' => $entry['memory'] ?? $entry['content'] ?? null,
            'score' => $entry['score'] ?? $entry['similarity'] ?? null,
            'metadata' => $entry['metadata'] ?? [],
        ];
    }

    private function extractContextSegments(array $entries, int $maxSegments = 16): array
    {
        $segments = [];

        foreach ($entries as $entry) {
            $title = is_string($entry['title'] ?? null) ? trim($entry['title']) : null;

            if (is_string($entry['memory'] ?? null) && trim($entry['memory']) !== '') {
                $segments[] = '[Memory] '.trim($entry['memory']);
            }

            if (is_string($entry['summary'] ?? null) && trim($entry['summary']) !== '') {
                $prefix = $title ? "[Document Summary: {$title}] " : '[Document Summary] ';
                $segments[] = $prefix.trim($entry['summary']);
            }

            if (is_string($entry['content'] ?? null) && trim($entry['content']) !== '') {
                $prefix = $title ? "[Document Content: {$title}] " : '[Document Content] ';
                $segments[] = $prefix.trim($entry['content']);
            }

            if (is_string($entry['chunk'] ?? null) && trim($entry['chunk']) !== '') {
                $prefix = $title ? "[Document Chunk: {$title}] " : '[Document Chunk] ';
                $segments[] = $prefix.trim($entry['chunk']);
            }

            foreach ($entry['chunks'] ?? [] as $chunk) {
                if (! is_array($chunk)) {
                    continue;
                }

                $chunkContent = $chunk['content'] ?? $chunk['chunk'] ?? null;

                if (! is_string($chunkContent) || trim($chunkContent) === '') {
                    continue;
                }

                $prefix = $title ? "[Document Chunk: {$title}] " : '[Document Chunk] ';
                $segments[] = $prefix.trim($chunkContent);
            }
        }

        $normalized = [];

        foreach ($segments as $segment) {
            $clean = trim(preg_replace('/\s+/', ' ', $segment) ?? '');

            if ($clean !== '') {
                $normalized[] = $clean;
            }
        }

        return array_slice(array_values(array_unique($normalized)), 0, $maxSegments);
    }

    private function isMemoryResult(array $entry): bool
    {
        if (array_key_exists('memory', $entry)) {
            return true;
        }

        $id = $entry['id'] ?? null;

        return is_string($id) && str_starts_with($id, 'mem_');
    }

    private function fallbackNoContextAnswer(): string
    {
        return 'I could not find relevant information in your documents or memories yet. Please add more content and try again.';
    }
}
