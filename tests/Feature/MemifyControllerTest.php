<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MocksCogneeService;
use Tests\TestCase;

class MemifyControllerTest extends TestCase
{
    use MocksCogneeService;
    use RefreshDatabase;

    public function test_store_runs_background_memify(): void
    {
        $user = User::factory()->create(['cognee_token' => 'cognee-token']);
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockCogneeService();
        /** @var \PHPUnit\Framework\MockObject\MockObject&\App\Services\CogneeService $mock */

        $mock->expects($this->once())
            ->method('memify')
            ->with(
                'cognee-token',
                null,
                'Docs',
                ['ExtractTopics'],
                ['SummarizeCommunities'],
                null,
                ['Project Phoenix'],
                true,
            )
            ->willReturn(['pipeline_run_id' => 'run-2']);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/memify', [
                'dataset_name' => 'Docs',
                'extraction_tasks' => ['ExtractTopics'],
                'enrichment_tasks' => ['SummarizeCommunities'],
                'node_name' => ['Project Phoenix'],
            ])
            ->assertOk()
            ->assertJsonPath('result.pipeline_run_id', 'run-2');
    }
}
