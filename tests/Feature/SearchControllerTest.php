<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MocksGeminiService;
use Tests\Concerns\MocksSupermemoryService;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use MocksGeminiService;
    use MocksSupermemoryService;
    use RefreshDatabase;

    public function test_search_memories_executes_memory_search_with_defaults(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockSupermemoryService();

        $mock->expects($this->once())
            ->method('searchMemories')
            ->with('What is this?', 'user-'.$user->id, 10, null, false)
            ->willReturn([
                'results' => [
                    [
                        'id' => 'mem-1',
                        'memory' => 'Remembered answer',
                        'score' => 0.92,
                        'metadata' => ['source' => 'memory'],
                    ],
                ],
                'total' => 1,
                'timing' => 87,
            ]);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/search/memories', [
                'query' => 'What is this?',
            ])
            ->assertOk()
            ->assertJsonPath('results.0.id', 'mem-1')
            ->assertJsonPath('results.0.content', 'Remembered answer')
            ->assertJsonPath('meta.search_mode', 'memories')
            ->assertJsonPath('meta.upstream_search_mode', 'hybrid')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_search_documents_validates_threshold_range(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/search/documents', [
                'query' => 'What is this?',
                'threshold' => 1.5,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['threshold']);
    }

    public function test_search_documents_passes_threshold_and_rerank_options(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;
        $supermemory = $this->mockSupermemoryService();
        $gemini = $this->mockGeminiService();

        $supermemory->expects($this->once())
            ->method('searchDocuments')
            ->with('What is this?', 'user-'.$user->id, 5, 0.6, true)
            ->willReturn([
                'results' => [
                    [
                        'documentId' => 'doc-9',
                        'title' => 'Contract',
                        'type' => 'pdf',
                        'content' => 'Renewal clause',
                        'score' => 0.88,
                        'metadata' => ['source' => 'file'],
                        'chunks' => [],
                    ],
                    [
                        'id' => 'mem_22',
                        'memory' => 'A related note from memory.',
                        'score' => 0.78,
                    ],
                ],
                'timing' => 54,
            ]);

        $gemini->expects($this->once())
            ->method('generateAnswer')
            ->with(
                'What is this?',
                $this->callback(function (array $segments): bool {
                    $joined = implode(' ', $segments);

                    return str_contains($joined, 'Renewal clause')
                        && str_contains($joined, 'A related note from memory');
                }),
                [
                    ['role' => 'user', 'content' => 'What document are we discussing?'],
                    ['role' => 'assistant', 'content' => 'We are discussing the contract.'],
                ],
            )
            ->willReturn('The contract has a renewal clause and related note.');

        $gemini->expects($this->once())
            ->method('model')
            ->willReturn('gemini-2.5-flash');

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/search/documents', [
                'query' => 'What is this?',
                'limit' => 5,
                'threshold' => 0.6,
                'rerank' => true,
                'conversationHistory' => [
                    ['role' => 'user', 'content' => 'What document are we discussing?'],
                    ['role' => 'assistant', 'content' => 'We are discussing the contract.'],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('answer', 'The contract has a renewal clause and related note.')
            ->assertJsonPath('meta.limit', 5)
            ->assertJsonPath('meta.threshold', 0.6)
            ->assertJsonPath('meta.rerank', true)
            ->assertJsonPath('meta.search_mode', 'documents')
            ->assertJsonPath('meta.response_mode', 'answer_only')
            ->assertJsonPath('meta.upstream_search_mode', 'hybrid')
            ->assertJsonPath('meta.model', 'gemini-2.5-flash')
            ->assertJsonPath('meta.context_items', 2)
            ->assertJsonPath('meta.conversation_history_items', 2)
            ->assertJsonPath('meta.no_context', false)
            ->assertJsonMissingPath('results');
    }

    public function test_search_documents_validates_conversation_history_shape(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/search/documents', [
                'query' => 'What is this?',
                'conversationHistory' => [
                    ['role' => 'system', 'content' => 'Invalid role'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['conversationHistory.0.role']);
    }

    public function test_search_documents_returns_graceful_fallback_when_no_context_found(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;
        $supermemory = $this->mockSupermemoryService();
        $gemini = $this->mockGeminiService();

        $supermemory->expects($this->once())
            ->method('searchDocuments')
            ->with('What is this?', 'user-'.$user->id, 10, null, false)
            ->willReturn([
                'results' => [],
                'timing' => 12,
            ]);

        $gemini->expects($this->never())
            ->method('generateAnswer');

        $gemini->expects($this->once())
            ->method('model')
            ->willReturn('gemini-2.5-flash');

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/search/documents', [
                'query' => 'What is this?',
            ])
            ->assertOk()
            ->assertJsonPath('answer', 'I could not find relevant information in your documents or memories yet. Please add more content and try again.')
            ->assertJsonPath('meta.search_mode', 'documents')
            ->assertJsonPath('meta.response_mode', 'answer_only')
            ->assertJsonPath('meta.no_context', true)
            ->assertJsonPath('meta.context_items', 0)
            ->assertJsonPath('meta.conversation_history_items', 0)
            ->assertJsonMissingPath('results');
    }
}
