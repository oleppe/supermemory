<?php

namespace Tests\Feature;

use App\Models\SupermemoryIngestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\MocksSupermemoryService;
use Tests\TestCase;

class IngestionControllerTest extends TestCase
{
    use MocksSupermemoryService;
    use RefreshDatabase;

    public function test_store_memory_adds_memory_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockSupermemoryService();

        $mock->expects($this->once())
            ->method('addMemory')
            ->with(
                'Hello Supermemory',
                'user-'.$user->id,
                $this->callback(function (array $metadata) use ($user) {
                    return $metadata['source'] === 'memory'
                        && $metadata['uploaded_by_user_id'] === $user->id;
                }),
                null,
            )
            ->willReturn(['id' => 'mem-1', 'status' => 'processed']);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/memories', [
                'text' => 'Hello Supermemory',
            ])
            ->assertCreated()
            ->assertJsonPath('memory.id', 'mem-1')
            ->assertJsonPath('memory.status', 'processed')
            ->assertJsonPath('memory.container_tag', 'user-'.$user->id);

        $this->assertDatabaseHas('supermemory_ingestions', [
            'user_id' => $user->id,
            'source_type' => 'memory',
            'source_name' => 'memory-entry',
            'supermemory_id' => 'mem-1',
            'supermemory_status' => 'processed',
        ]);
    }

    public function test_store_documents_uploads_file_and_optionally_saves_summary_as_memory(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;
        $file = UploadedFile::fake()->createWithContent('doc.txt', 'file-body');
        $mock = $this->mockSupermemoryService();

        $mock->expects($this->once())
            ->method('uploadFile')
            ->with(
                $this->callback(fn (UploadedFile $uploadedFile) => $uploadedFile->getClientOriginalName() === 'doc.txt'),
                'user-'.$user->id,
                $this->callback(function (array $metadata) use ($user) {
                    return $metadata['source'] === 'file'
                        && $metadata['original_name'] === 'doc.txt'
                        && $metadata['uploaded_by_user_id'] === $user->id;
                }),
                'custom-file',
                'Project docs',
            )
            ->willReturn(['id' => 'doc-2', 'status' => 'queued']);

        $mock->expects($this->once())
            ->method('addMemory')
            ->with(
                'Short summary',
                'user-'.$user->id,
                $this->callback(fn (array $metadata) => ($metadata['source'] ?? null) === 'document_summary' && ($metadata['linked_document_id'] ?? null) === 'doc-2'),
                'summary-1',
                'Summary context',
            )
            ->willReturn(['id' => 'mem-2', 'status' => 'processed']);

        $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/documents', [
                'files' => [$file],
                'custom_id' => 'custom-file',
                'entity_context' => 'Project docs',
                'summary' => 'Short summary',
                'summary_custom_id' => 'summary-1',
                'summary_entity_context' => 'Summary context',
            ])
            ->assertCreated()
            ->assertJsonPath('documents.0.id', 'doc-2')
            ->assertJsonPath('documents.0.name', 'doc.txt')
            ->assertJsonPath('summary_memory.id', 'mem-2');

        $this->assertDatabaseHas('supermemory_ingestions', [
            'user_id' => $user->id,
            'source_type' => 'document',
            'source_name' => 'doc.txt',
            'supermemory_id' => 'doc-2',
            'supermemory_status' => 'queued',
        ]);

        $summary = SupermemoryIngestion::where('supermemory_id', 'mem-2')->first();
        $this->assertNotNull($summary);
        $this->assertSame('memory', $summary->source_type);
        $this->assertSame('doc-2', $summary->linked_supermemory_id);
    }

    public function test_store_documents_rejects_summary_for_multiple_files(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;
        $first = UploadedFile::fake()->create('a.txt');
        $second = UploadedFile::fake()->create('b.txt');

        $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/documents', [
                'files' => [$first, $second],
                'summary' => 'Short summary',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['summary']);
    }

    public function test_store_memory_requires_text(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/memories', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['text']);
    }
}
