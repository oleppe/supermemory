<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\MocksCogneeService;
use Tests\TestCase;
use App\Exceptions\CogneeApiException;

class IngestionControllerTest extends TestCase
{
    use MocksCogneeService;
    use RefreshDatabase;

    public function test_store_text_creates_dataset_and_adds_text(): void
    {
        $user = User::factory()->create(['cognee_token' => 'cognee-token']);
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockCogneeService();

        $mock->expects($this->once())
            ->method('createDataset')
            ->with('cognee-token', 'Notes')
            ->willReturn(['id' => 'dataset-1', 'name' => 'Notes']);

        $mock->expects($this->once())
            ->method('addData')
            ->with(
                'cognee-token',
                'Hello Cognee',
                'dataset-1',
                'Notes',
                [],
                null,
            )
            ->willReturn(['status' => 'added']);

        $mock->expects($this->once())
            ->method('cognify')
            ->with('cognee-token', 'dataset-1', 'Notes', true, null, [])
            ->willReturn(['pipeline_run_id' => 'run-1']);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/ingestion/text', [
                'text' => 'Hello Cognee',
                'dataset_name' => 'Notes',
            ])
            ->assertCreated()
            ->assertJsonPath('dataset.name', 'Notes')
            ->assertJsonPath('result.status', 'added')
            ->assertJsonPath('cognify.triggered', true)
            ->assertJsonPath('cognify.result.pipeline_run_id', 'run-1');
    }

    public function test_store_text_restores_expired_cognee_session_and_retries(): void
    {
        $user = User::factory()->create([
            'cognee_token' => 'expired-token',
            'cognee_password' => 'password123',
        ]);
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockCogneeService();

        $mock->expects($this->exactly(2))
            ->method('createDataset')
            ->with($this->logicalOr('expired-token', 'restored-token'), 'Notes')
            ->willReturnCallback(function (string $token) {
                if ($token === 'expired-token') {
                    throw CogneeApiException::sessionExpired();
                }

                return ['id' => 'dataset-1', 'name' => 'Notes'];
            });

        $mock->expects($this->once())
            ->method('addData')
            ->with(
                'restored-token',
                'Hello Cognee',
                'dataset-1',
                'Notes',
                [],
                null,
            )
            ->willReturn(['status' => 'added']);

        $mock->expects($this->once())
            ->method('cognify')
            ->with('restored-token', 'dataset-1', 'Notes', true, null, [])
            ->willReturn(['pipeline_run_id' => 'run-1']);

        $mock->expects($this->once())
            ->method('restoreUserSession')
            ->with($this->callback(fn (User $model) => $model->is($user)))
            ->willReturn('restored-token');

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/ingestion/text', [
                'text' => 'Hello Cognee',
                'dataset_name' => 'Notes',
            ])
            ->assertCreated()
            ->assertJsonPath('dataset.name', 'Notes')
            ->assertJsonPath('result.status', 'added')
            ->assertJsonPath('cognify.triggered', true)
            ->assertJsonPath('cognify.result.pipeline_run_id', 'run-1');
    }

    public function test_store_files_passes_uploaded_file_contents(): void
    {
        $datasetId = '123e4567-e89b-12d3-a456-426614174000';
        $user = User::factory()->create(['cognee_token' => 'cognee-token']);
        $token = $user->createToken('flutter')->plainTextToken;
        $file = UploadedFile::fake()->createWithContent('doc.txt', 'file-body');
        $mock = $this->mockCogneeService();

        $mock->expects($this->never())
            ->method('createDataset');

        $mock->expects($this->once())
            ->method('addData')
            ->with(
                'cognee-token',
                'file-body',
                $datasetId,
                null,
                [],
                'doc.txt',
            )
            ->willReturn(['status' => 'added']);

        $mock->expects($this->once())
            ->method('cognify')
            ->with('cognee-token', $datasetId, null, true, null, [])
            ->willReturn(['pipeline_run_id' => 'run-2']);

        $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/ingestion/files', [
                'files' => [$file],
                'dataset_id' => $datasetId,
            ])
            ->assertCreated()
            ->assertJsonPath('result.status', 'added')
            ->assertJsonPath('cognify.triggered', true)
            ->assertJsonPath('cognify.result.pipeline_run_id', 'run-2');
    }

    public function test_store_text_can_skip_cognify_when_requested(): void
    {
        $user = User::factory()->create(['cognee_token' => 'cognee-token']);
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockCogneeService();

        $mock->expects($this->once())
            ->method('createDataset')
            ->with('cognee-token', 'Notes')
            ->willReturn(['id' => 'dataset-1', 'name' => 'Notes']);

        $mock->expects($this->once())
            ->method('addData')
            ->with(
                'cognee-token',
                'Hello Cognee',
                'dataset-1',
                'Notes',
                [],
                null,
            )
            ->willReturn(['status' => 'added']);

        $mock->expects($this->never())
            ->method('cognify');

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/ingestion/text', [
                'text' => 'Hello Cognee',
                'dataset_name' => 'Notes',
                'run_cognify' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('cognify.triggered', false)
            ->assertJsonPath('cognify.reason', 'disabled');
    }

    public function test_store_text_requires_dataset_selector(): void
    {
        $user = User::factory()->create(['cognee_token' => 'cognee-token']);
        $token = $user->createToken('flutter')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/ingestion/text', ['text' => 'Hello'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['dataset_id', 'dataset_name']);
    }
}
