<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MocksCogneeService;
use Tests\TestCase;

class CognifyControllerTest extends TestCase
{
    use MocksCogneeService;
    use RefreshDatabase;

    public function test_store_runs_background_cognify(): void
    {
        $user = User::factory()->create(['cognee_token' => 'cognee-token']);
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockCogneeService();

        $mock->expects($this->once())
            ->method('createDataset')
            ->with('cognee-token', 'Docs')
            ->willReturn(['id' => 'dataset-1', 'name' => 'Docs']);

        $mock->expects($this->once())
            ->method('cognify')
            ->with('cognee-token', 'dataset-1', 'Docs', true, 'Extract relations', ['schema'])
            ->willReturn(['pipeline_run_id' => 'run-1']);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/cognify', [
                'dataset_name' => 'Docs',
                'custom_prompt' => 'Extract relations',
                'ontology_key' => ['schema'],
            ])
            ->assertOk()
            ->assertJsonPath('result.pipeline_run_id', 'run-1');
    }
}
