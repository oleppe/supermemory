<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MocksCogneeService;
use Tests\TestCase;

class DatasetControllerTest extends TestCase
{
    use MocksCogneeService;
    use RefreshDatabase;

    public function test_index_returns_datasets(): void
    {
        $user = User::factory()->create(['cognee_token' => 'cognee-token']);
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockCogneeService();

        $mock->expects($this->once())
            ->method('getDatasets')
            ->with('cognee-token')
            ->willReturn([
                ['id' => 'dataset-1', 'name' => 'Docs'],
            ]);

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/datasets')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Docs');
    }

    public function test_index_restores_expired_cognee_session_and_retries(): void
    {
        $user = User::factory()->create([
            'cognee_token' => 'expired-token',
            'cognee_password' => 'password123',
        ]);
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockCogneeService();

        $mock->expects($this->exactly(2))
            ->method('getDatasets')
            ->with($this->logicalOr('expired-token', 'restored-token'))
            ->willReturnCallback(function (string $token) {
                if ($token === 'expired-token') {
                    throw \App\Exceptions\CogneeApiException::sessionExpired();
                }

                return [
                    ['id' => 'dataset-1', 'name' => 'Docs'],
                ];
            });

        $mock->expects($this->once())
            ->method('restoreUserSession')
            ->with($this->callback(fn (User $model) => $model->is($user)))
            ->willReturn('restored-token');

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/datasets')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Docs');
    }

    public function test_store_creates_dataset(): void
    {
        $user = User::factory()->create(['cognee_token' => 'cognee-token']);
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockCogneeService();

        $mock->expects($this->once())
            ->method('createDataset')
            ->with('cognee-token', 'Research')
            ->willReturn(['id' => 'dataset-1', 'name' => 'Research']);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/datasets', ['name' => 'Research'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Research');
    }

    public function test_status_maps_upstream_states(): void
    {
        $datasetId = '123e4567-e89b-12d3-a456-426614174000';
        $user = User::factory()->create(['cognee_token' => 'cognee-token']);
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockCogneeService();

        $mock->expects($this->once())
            ->method('getDatasetStatus')
            ->with('cognee-token', [$datasetId])
            ->willReturn([
                $datasetId => 'DATASET_PROCESSING_STARTED',
            ]);

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/datasets/status?dataset_ids[0]='.$datasetId)
            ->assertOk()
            ->assertJsonPath('data.'.$datasetId.'.state', 'processing');
    }
}
