<?php

namespace App\Http\Controllers;

use App\Services\SupermemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function __construct(
        private readonly SupermemoryService $supermemoryService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'sort' => ['nullable', 'string', 'in:createdAt,updatedAt'],
            'order' => ['nullable', 'string', 'in:asc,desc'],
        ]);

        $user = $this->authenticatedUser($request);
        $result = $this->supermemoryService->listDocuments(
            $this->supermemoryContainerTag($user),
            $validated['page'] ?? 1,
            $validated['limit'] ?? 10,
            $validated['sort'] ?? 'createdAt',
            $validated['order'] ?? 'desc',
        );

        $documents = array_map(
            fn (array $document): array => $this->normalizeDocument($document),
            $result['documents'] ?? $result['memories'] ?? [],
        );

        return response()->json([
            'data' => $documents,
            'meta' => [
                'page' => $validated['page'] ?? 1,
                'limit' => $validated['limit'] ?? 10,
                'pagination' => $result['pagination'] ?? null,
            ],
        ]);
    }

    public function show(Request $request, string $documentId): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $document = $this->supermemoryService->getDocument($documentId);

        if (! $this->belongsToUser($document, $this->supermemoryContainerTag($user))) {
            return response()->json([
                'message' => 'Document not found',
            ], 404);
        }

        return response()->json([
            'document' => $this->normalizeDocument($document),
        ]);
    }

    private function belongsToUser(array $document, string $containerTag): bool
    {
        $tags = $document['containerTags'] ?? [];

        return is_array($tags) && in_array($containerTag, $tags, true);
    }

    private function normalizeDocument(array $document): array
    {
        return [
            'id' => $document['id'] ?? null,
            'status' => $document['status'] ?? $document['workflowStatus'] ?? null,
            'title' => $document['title'] ?? $document['name'] ?? null,
            'type' => $document['type'] ?? null,
            'content' => $document['content'] ?? null,
            'summary' => $document['summary'] ?? null,
            'custom_id' => $document['customId'] ?? null,
            'metadata' => $document['metadata'] ?? [],
            'container_tags' => $document['containerTags'] ?? [],
            'created_at' => $document['createdAt'] ?? null,
            'updated_at' => $document['updatedAt'] ?? null,
        ];
    }
}
