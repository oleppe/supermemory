<?php

namespace Tests\Feature;

use App\Exceptions\CogneeApiException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MocksCogneeService;
use Tests\TestCase;

class SearchControllerTest extends TestCase
{
    use MocksCogneeService;
    use RefreshDatabase;

    public function test_store_executes_search_with_defaults(): void
    {
        $user = User::factory()->create(['cognee_token' => 'cognee-token']);
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockCogneeService();

        $mock->expects($this->once())
            ->method('search')
            ->with('cognee-token', 'What is this?', null, 'Docs', 'GRAPH_COMPLETION', 10, false, null, [])
            ->willReturn([
                ['search_result' => 'Answer'],
            ]);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/search', [
                'query' => 'What is this?',
                'dataset_name' => 'Docs',
            ])
            ->assertOk()
            ->assertJsonPath('results.0.search_result', 'Answer')
            ->assertJsonPath('meta.search_type', 'GRAPH_COMPLETION');
    }

    public function test_history_returns_search_history(): void
    {
        $user = User::factory()->create(['cognee_token' => 'cognee-token']);
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockCogneeService();

        $mock->expects($this->once())
            ->method('getSearchHistory')
            ->with('cognee-token')
            ->willReturn([
                ['id' => 'history-1', 'text' => 'previous query'],
            ]);

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/search/history')
            ->assertOk()
            ->assertJsonPath('data.0.text', 'previous query');
    }

    public function test_store_validates_search_type(): void
    {
        $user = User::factory()->create(['cognee_token' => 'cognee-token']);
        $token = $user->createToken('flutter')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/search', [
                'query' => 'What is this?',
                'dataset_name' => 'Docs',
                'search_type' => 'INVALID',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['search_type']);
    }

    public function test_store_returns_actionable_error_when_dataset_needs_cognify(): void
    {
        $user = User::factory()->create(['cognee_token' => 'cognee-token']);
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockCogneeService();

        $mock->expects($this->once())
            ->method('search')
            ->willThrowException(new CogneeApiException(
                statusCode: 404,
                cogneeDetail: "Dataset 'Docs' has 1 data item(s) but the knowledge graph is empty. Please run cognify to process the data before searching.",
                message: 'NoDataError',
            ));

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/search', [
                'query' => 'What is this?',
                'dataset_name' => 'Docs',
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Search requires cognify to be run first')
            ->assertJsonPath('detail.code', 'SEARCH_REQUIRES_COGNIFY')
            ->assertJsonPath('detail.action', 'run_cognify')
            ->assertJsonPath('detail.dataset_name', 'Docs');
    }

    public function test_store_restores_expired_cognee_session_and_retries_search(): void
    {
        $user = User::factory()->create([
            'cognee_token' => 'expired-token',
            'cognee_password' => 'password123',
        ]);
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockCogneeService();

        $mock->expects($this->exactly(2))
            ->method('search')
            ->with(
                $this->logicalOr('expired-token', 'restored-token'),
                'What is this?',
                null,
                'Docs',
                'GRAPH_COMPLETION',
                10,
                false,
                null,
                []
            )
            ->willReturnCallback(function (string $token) {
                if ($token === 'expired-token') {
                    throw CogneeApiException::sessionExpired();
                }

                return [['search_result' => 'Recovered answer']];
            });

        $mock->expects($this->once())
            ->method('restoreUserSession')
            ->with($this->callback(fn (User $model) => $model->is($user)))
            ->willReturn('restored-token');

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/search', [
                'query' => 'What is this?',
                'dataset_name' => 'Docs',
            ])
            ->assertOk()
            ->assertJsonPath('results.0.search_result', 'Recovered answer');
    }
}
