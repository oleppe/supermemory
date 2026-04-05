<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MocksSupermemoryService;
use Tests\TestCase;

class DocumentControllerTest extends TestCase
{
    use MocksSupermemoryService;
    use RefreshDatabase;

    public function test_index_lists_documents_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockSupermemoryService();

        $mock->expects($this->once())
            ->method('listDocuments')
            ->with('user-'.$user->id, 1, 10, 'createdAt', 'desc')
            ->willReturn([
                'documents' => [
                    [
                        'id' => 'doc-1',
                        'title' => 'Meeting notes',
                        'status' => 'done',
                        'type' => 'text',
                        'containerTags' => ['user-'.$user->id],
                        'metadata' => ['source' => 'text'],
                        'createdAt' => '2026-04-02T10:00:00Z',
                        'updatedAt' => '2026-04-02T10:00:00Z',
                    ],
                ],
            ]);

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/documents')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'doc-1')
            ->assertJsonPath('data.0.title', 'Meeting notes')
            ->assertJsonPath('data.0.status', 'done');
    }

    public function test_show_returns_document_when_it_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockSupermemoryService();

        $mock->expects($this->once())
            ->method('getDocument')
            ->with('doc-1')
            ->willReturn([
                'id' => 'doc-1',
                'title' => 'Meeting notes',
                'status' => 'processing',
                'type' => 'text',
                'containerTags' => ['user-'.$user->id],
                'metadata' => ['source' => 'text'],
            ]);

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/documents/doc-1')
            ->assertOk()
            ->assertJsonPath('document.id', 'doc-1')
            ->assertJsonPath('document.status', 'processing');
    }

    public function test_show_returns_not_found_for_other_users_document(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockSupermemoryService();

        $mock->expects($this->once())
            ->method('getDocument')
            ->with('doc-1')
            ->willReturn([
                'id' => 'doc-1',
                'containerTags' => ['user-999'],
            ]);

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/documents/doc-1')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Document not found');
    }
}
