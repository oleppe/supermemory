<?php

namespace Tests\Feature;

use App\Models\SupermemoryIngestion;
use App\Models\User;
use App\Services\FirestoreSyncService;
use App\Services\SupermemoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncSupermemoryStatusesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_updates_pending_document_statuses(): void
    {
        $user = User::factory()->create();

        $record = SupermemoryIngestion::create([
            'user_id' => $user->id,
            'source_type' => 'document',
            'source_name' => 'contract.pdf',
            'supermemory_id' => 'doc-123',
            'supermemory_status' => 'queued',
            'metadata' => ['source' => 'file'],
            'last_synced_at' => now()->subMinute(),
        ]);

        $supermemory = $this->createMock(SupermemoryService::class);
        $supermemory->expects($this->once())
            ->method('isConfigured')
            ->willReturn(true);
        $supermemory->expects($this->once())
            ->method('getDocument')
            ->with('doc-123')
            ->willReturn([
                'id' => 'doc-123',
                'status' => 'done',
            ]);
        $this->app->instance(SupermemoryService::class, $supermemory);

        $firestore = $this->createMock(FirestoreSyncService::class);
        $firestore->expects($this->once())
            ->method('upsertIngestion')
            ->with($this->callback(fn (SupermemoryIngestion $ingestion) => $ingestion->id === $record->id && $ingestion->supermemory_status === 'done'));
        $this->app->instance(FirestoreSyncService::class, $firestore);

        $this->artisan('supermemory:sync-statuses --limit=10')
            ->assertExitCode(0);

        $this->assertDatabaseHas('supermemory_ingestions', [
            'id' => $record->id,
            'supermemory_status' => 'done',
            'error_message' => null,
        ]);
    }

    public function test_command_skips_when_supermemory_is_not_configured(): void
    {
        $supermemory = $this->createMock(SupermemoryService::class);
        $supermemory->expects($this->once())
            ->method('isConfigured')
            ->willReturn(false);
        $this->app->instance(SupermemoryService::class, $supermemory);

        $firestore = $this->createMock(FirestoreSyncService::class);
        $firestore->expects($this->never())->method('upsertIngestion');
        $this->app->instance(FirestoreSyncService::class, $firestore);

        $this->artisan('supermemory:sync-statuses --limit=10')
            ->assertExitCode(0);
    }
}
