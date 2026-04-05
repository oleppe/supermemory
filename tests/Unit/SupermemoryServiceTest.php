<?php

namespace Tests\Unit;

use App\Services\SupermemoryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupermemoryServiceTest extends TestCase
{
    public function test_add_memory_sends_expected_payload(): void
    {
        config()->set('supermemory.base_url', 'https://api.supermemory.ai');
        config()->set('supermemory.api_key', 'sm-test-key');

        Http::fake([
            'https://api.supermemory.ai/v4/memories' => Http::response([
                'documentId' => 'doc-1',
                'memories' => [
                    ['id' => 'mem-1', 'memory' => 'Hello Supermemory', 'isStatic' => false],
                ],
            ], 201),
        ]);

        $service = new SupermemoryService;
        $result = $service->addMemory(
            'Hello Supermemory',
            'user-1',
            ['source' => 'text'],
            'note-1',
            'Important notes',
        );

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.supermemory.ai/v4/memories'
                && $request->hasHeader('Authorization', 'Bearer sm-test-key')
                && isset($request['memories'][0])
                && $request['memories'][0]['content'] === 'Hello Supermemory'
                && $request['memories'][0]['isStatic'] === false
                && $request['containerTag'] === 'user-1'
                && $request['memories'][0]['metadata'] === [
                    'source' => 'text',
                    'custom_id' => 'note-1',
                    'entity_context' => 'Important notes',
                ];
        });

        $this->assertSame('mem-1', $result['id'] ?? null);
        $this->assertSame('processed', $result['status'] ?? null);
    }

    public function test_upload_file_posts_multipart_payload(): void
    {
        config()->set('supermemory.base_url', 'https://api.supermemory.ai');
        config()->set('supermemory.api_key', 'sm-test-key');

        Http::fake([
            'https://api.supermemory.ai/v3/documents/file' => Http::response(['id' => 'doc-2', 'status' => 'queued'], 200),
        ]);

        $service = new SupermemoryService;
        $file = UploadedFile::fake()->createWithContent('report.txt', 'file-body');
        $result = $service->uploadFile(
            $file,
            'user-1',
            ['source' => 'file'],
            'report-1',
            'Quarterly reports',
        );

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.supermemory.ai/v3/documents/file'
                && $request->hasHeader('Authorization', 'Bearer sm-test-key')
                && str_contains($request->body(), 'filename="report.txt"')
                && str_contains($request->body(), 'name="containerTags"')
                && str_contains($request->body(), 'user-1')
                && str_contains($request->body(), 'report-1')
                && str_contains($request->body(), 'Quarterly reports');
        });

        $this->assertSame(['id' => 'doc-2', 'status' => 'queued'], $result);
    }

    public function test_list_documents_filters_by_container_tag(): void
    {
        config()->set('supermemory.base_url', 'https://api.supermemory.ai');
        config()->set('supermemory.api_key', 'sm-test-key');

        Http::fake([
            'https://api.supermemory.ai/v3/documents/list' => Http::response(['documents' => []], 200),
        ]);

        $service = new SupermemoryService;
        $service->listDocuments('user-1', 2, 5, 'updatedAt', 'asc');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.supermemory.ai/v3/documents/list'
                && $request->hasHeader('Authorization', 'Bearer sm-test-key')
                && $request['containerTags'] === ['user-1']
                && $request['page'] === 2
                && $request['limit'] === 5
                && $request['sort'] === 'updatedAt'
                && $request['order'] === 'asc';
        });
    }

    public function test_search_memories_uses_memory_search_mode(): void
    {
        config()->set('supermemory.base_url', 'https://api.supermemory.ai');
        config()->set('supermemory.api_key', 'sm-test-key');

        Http::fake([
            'https://api.supermemory.ai/v4/search' => Http::response(['results' => [], 'total' => 0, 'timing' => 42], 200),
        ]);

        $service = new SupermemoryService;
        $service->searchMemories('where is the contract?', 'user-1', 7, 0.7, true);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.supermemory.ai/v4/search'
                && $request->hasHeader('Authorization', 'Bearer sm-test-key')
                && $request['q'] === 'where is the contract?'
                && $request['containerTag'] === 'user-1'
                && $request['limit'] === 7
                && $request['rerank'] === true
                && $request['searchMode'] === 'hybrid'
                && $request['threshold'] === 0.7;
        });
    }

    public function test_search_documents_uses_documents_search_mode(): void
    {
        config()->set('supermemory.base_url', 'https://api.supermemory.ai');
        config()->set('supermemory.api_key', 'sm-test-key');

        Http::fake([
            'https://api.supermemory.ai/v4/search' => Http::response(['results' => [], 'total' => 0, 'timing' => 42], 200),
        ]);

        $service = new SupermemoryService;
        $service->searchDocuments('where is the contract?', 'user-1', 7, 0.7, true);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.supermemory.ai/v4/search'
                && $request->hasHeader('Authorization', 'Bearer sm-test-key')
                && $request['q'] === 'where is the contract?'
                && $request['containerTag'] === 'user-1'
                && $request['limit'] === 7
                && $request['rerank'] === true
                && $request['searchMode'] === 'hybrid'
                && $request['threshold'] === 0.7
                && $request['includeFullDocs'] === true
                && $request['onlyMatchingChunks'] === true;
        });
    }
}
