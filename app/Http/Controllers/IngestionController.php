<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddFilesRequest;
use App\Http\Requests\AddTextRequest;
use App\Models\SupermemoryIngestion;
use App\Services\FirestoreSyncService;
use App\Services\SupermemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class IngestionController extends Controller
{
    public function __construct(
        private readonly FirestoreSyncService $firestoreSyncService,
        private readonly SupermemoryService $supermemoryService,
    ) {}

    public function storeMemory(AddTextRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();
        $containerTag = $this->supermemoryContainerTag($user);
        $metadata = array_merge($validated['metadata'] ?? [], [
            'source' => 'memory',
            'uploaded_by_user_id' => $user->id,
        ]);

        $memory = $this->supermemoryService->addMemory(
            $validated['text'],
            $containerTag,
            $metadata,
            $validated['custom_id'] ?? null,
            $validated['entity_context'] ?? null,
        );

        $record = $this->storeIngestionRecord(
            userId: $user->id,
            sourceType: 'memory',
            sourceName: $validated['custom_id'] ?? 'memory-entry',
            supermemoryId: (string) ($memory['id'] ?? ''),
            supermemoryStatus: (string) ($memory['status'] ?? 'processed'),
            customId: $validated['custom_id'] ?? null,
            linkedSupermemoryId: null,
            metadata: $metadata,
        );

        if ($record) {
            $this->firestoreSyncService->upsertIngestion($record);
        }

        return response()->json([
            'memory' => [
                'id' => $memory['id'] ?? null,
                'status' => $memory['status'] ?? null,
                'container_tag' => $containerTag,
                'custom_id' => $validated['custom_id'] ?? null,
                'metadata' => $metadata,
            ],
        ], 201);
    }

    public function storeDocuments(AddFilesRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();
        $containerTag = $this->supermemoryContainerTag($user);
        $documents = [];
        $files = $request->file('files');

        if (($validated['summary'] ?? null) !== null && count($files) !== 1) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'summary' => ['A summary can only be attached when uploading exactly one document.'],
                ],
            ], 422);
        }

        foreach ($files as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $customId = $validated['custom_id'] ?? null;

            if ($customId !== null && count($files) > 1) {
                $customId .= '-'.($index + 1);
            }

            $metadata = array_merge($validated['metadata'] ?? [], [
                'source' => 'file',
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'uploaded_by_user_id' => $user->id,
            ]);

            $document = $this->supermemoryService->uploadFile(
                $file,
                $containerTag,
                $metadata,
                $customId,
                $validated['entity_context'] ?? null,
            );

            $documents[] = [
                'id' => $document['id'] ?? null,
                'status' => $document['status'] ?? null,
                'name' => $file->getClientOriginalName(),
                'container_tag' => $containerTag,
                'custom_id' => $customId,
                'metadata' => $metadata,
            ];

            $record = $this->storeIngestionRecord(
                userId: $user->id,
                sourceType: 'document',
                sourceName: $file->getClientOriginalName(),
                supermemoryId: (string) ($document['id'] ?? ''),
                supermemoryStatus: (string) ($document['status'] ?? 'queued'),
                customId: $customId,
                linkedSupermemoryId: null,
                metadata: $metadata,
            );

            if ($record) {
                $this->firestoreSyncService->upsertIngestion($record);
            }
        }

        $summaryMemory = null;

        if (($validated['summary'] ?? null) !== null && $documents !== []) {
            $summaryMetadata = array_merge($validated['summary_metadata'] ?? [], [
                'source' => 'document_summary',
                'uploaded_by_user_id' => $user->id,
                'linked_document_id' => $documents[0]['id'],
                'original_name' => $documents[0]['name'],
            ]);

            $summaryCustomId = $validated['summary_custom_id'] ?? null;

            if ($summaryCustomId === null && ($validated['custom_id'] ?? null) !== null) {
                $summaryCustomId = $validated['custom_id'].'-summary';
            }

            $memory = $this->supermemoryService->addMemory(
                $validated['summary'],
                $containerTag,
                $summaryMetadata,
                $summaryCustomId,
                $validated['summary_entity_context'] ?? $validated['entity_context'] ?? null,
            );

            $summaryMemory = [
                'id' => $memory['id'] ?? null,
                'status' => $memory['status'] ?? null,
                'container_tag' => $containerTag,
                'custom_id' => $summaryCustomId,
                'metadata' => $summaryMetadata,
            ];

            $record = $this->storeIngestionRecord(
                userId: $user->id,
                sourceType: 'memory',
                sourceName: ($documents[0]['name'] ?? 'document').' summary',
                supermemoryId: (string) ($memory['id'] ?? ''),
                supermemoryStatus: (string) ($memory['status'] ?? 'processed'),
                customId: $summaryCustomId,
                linkedSupermemoryId: $documents[0]['id'] ?? null,
                metadata: $summaryMetadata,
            );

            if ($record) {
                $this->firestoreSyncService->upsertIngestion($record);
            }
        }

        return response()->json([
            'documents' => $documents,
            'summary_memory' => $summaryMemory,
        ], 201);
    }

    private function storeIngestionRecord(
        int $userId,
        string $sourceType,
        ?string $sourceName,
        string $supermemoryId,
        string $supermemoryStatus,
        ?string $customId,
        ?string $linkedSupermemoryId,
        array $metadata,
    ): ?SupermemoryIngestion {
        if ($supermemoryId === '') {
            return null;
        }

        return SupermemoryIngestion::updateOrCreate(
            ['supermemory_id' => $supermemoryId],
            [
                'user_id' => $userId,
                'source_type' => $sourceType,
                'source_name' => $sourceName,
                'supermemory_status' => $supermemoryStatus,
                'custom_id' => $customId,
                'linked_supermemory_id' => $linkedSupermemoryId,
                'metadata' => $metadata,
                'last_synced_at' => now(),
                'error_message' => null,
            ],
        );
    }
}
