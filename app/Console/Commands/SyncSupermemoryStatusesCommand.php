<?php

namespace App\Console\Commands;

use App\Models\SupermemoryIngestion;
use App\Services\FirestoreSyncService;
use App\Services\SupermemoryService;
use Illuminate\Console\Command;
use Throwable;

class SyncSupermemoryStatusesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'supermemory:sync-statuses {--limit=200 : Max number of pending records to process per run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync pending Supermemory document statuses and propagate updates to Firestore';

    public function __construct(
        private readonly FirestoreSyncService $firestoreSyncService,
        private readonly SupermemoryService $supermemoryService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->supermemoryService->isConfigured()) {
            $this->warn('SUPERMEMORY_API_KEY is not configured. Skipping status sync.');

            return self::SUCCESS;
        }

        $limit = (int) $this->option('limit');
        $processed = 0;
        $updated = 0;

        $pending = SupermemoryIngestion::query()
            ->where('source_type', 'document')
            ->whereNotIn('supermemory_status', ['done', 'failed'])
            ->orderBy('id')
            ->limit(max($limit, 1))
            ->get();

        foreach ($pending as $ingestion) {
            $processed++;

            try {
                $document = $this->supermemoryService->getDocument($ingestion->supermemory_id);
                $status = $this->extractStatus($document);

                if ($status === null) {
                    continue;
                }

                $hasChanged = $status !== $ingestion->supermemory_status;

                $ingestion->supermemory_status = $status;
                $ingestion->last_synced_at = now();
                $ingestion->error_message = null;
                $ingestion->save();

                if ($hasChanged) {
                    $updated++;
                }

                $this->firestoreSyncService->upsertIngestion($ingestion);
            } catch (Throwable $exception) {
                $ingestion->error_message = $exception->getMessage();
                $ingestion->last_synced_at = now();
                $ingestion->save();
            }
        }

        $this->info(sprintf('Processed %d pending document statuses, updated %d.', $processed, $updated));

        return self::SUCCESS;
    }

    private function extractStatus(array $document): ?string
    {
        $raw = $document['status'] ?? $document['workflowStatus'] ?? null;

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $normalized = strtolower($raw);

        return match ($normalized) {
            'processed' => 'done',
            default => $normalized,
        };
    }
}
