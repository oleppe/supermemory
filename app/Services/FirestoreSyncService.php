<?php

namespace App\Services;

use App\Models\SupermemoryIngestion;
use Illuminate\Support\Facades\Log;
use Throwable;

class FirestoreSyncService
{
    private mixed $client = null;

    private bool $disabled = false;

    private bool $skipReasonLogged = false;

    public function isConfigured(): bool
    {
        return filled(config('services.firebase.project_id'))
            && (filled(config('services.firebase.service_account_json')) || filled(config('services.firebase.service_account_path')));
    }

    public function upsertIngestion(SupermemoryIngestion $ingestion): void
    {
        $client = $this->client();

        if (! $client) {
            return;
        }

        try {
            $collection = (string) config('services.firebase.collection_root', 'user_ingestions');

            $client
                ->collection($collection)
                ->document((string) $ingestion->user_id)
                ->collection('items')
                ->document((string) $ingestion->id)
                ->set([
                    'id' => $ingestion->id,
                    'user_id' => $ingestion->user_id,
                    'source_type' => $ingestion->source_type,
                    'source_name' => $ingestion->source_name,
                    'supermemory_id' => $ingestion->supermemory_id,
                    'supermemory_status' => $ingestion->supermemory_status,
                    'custom_id' => $ingestion->custom_id,
                    'linked_supermemory_id' => $ingestion->linked_supermemory_id,
                    'metadata' => $ingestion->metadata ?? [],
                    'last_synced_at' => optional($ingestion->last_synced_at)?->toIso8601String(),
                    'updated_at' => optional($ingestion->updated_at)?->toIso8601String(),
                    'created_at' => optional($ingestion->created_at)?->toIso8601String(),
                ], [
                    'merge' => true,
                ]);
        } catch (Throwable $exception) {
            Log::warning('Firestore ingestion sync failed', [
                'ingestion_id' => $ingestion->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function client(): mixed
    {
        if ($this->disabled) {
            return null;
        }

        if ($this->client !== null) {
            return $this->client;
        }

        $firestoreClientClass = 'Google\\Cloud\\Firestore\\FirestoreClient';

        if (! $this->isConfigured()) {
            $this->logSkipReason('Firestore sync skipped: Firebase configuration is incomplete.', [
                'project_id_set' => filled(config('services.firebase.project_id')),
                'service_account_json_set' => filled(config('services.firebase.service_account_json')),
                'service_account_path_set' => filled(config('services.firebase.service_account_path')),
            ]);

            return null;
        }

        if (! class_exists($firestoreClientClass)) {
            $this->logSkipReason('Firestore sync skipped: google/cloud-firestore client class is unavailable.');

            return null;
        }

        $keyFile = $this->resolveKeyFile();

        if (! is_array($keyFile)) {
            $this->logSkipReason('Firestore sync skipped: unable to parse service account credentials.', [
                'service_account_path' => config('services.firebase.service_account_path'),
            ]);

            $this->disabled = true;

            return null;
        }

        try {
            $this->client = new $firestoreClientClass([
                'projectId' => (string) config('services.firebase.project_id'),
                'keyFile' => $keyFile,
            ]);
        } catch (Throwable $exception) {
            $this->disabled = true;

            Log::warning('Firestore client initialization failed', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        return $this->client;
    }

    private function resolveKeyFile(): ?array
    {
        $path = config('services.firebase.service_account_path');

        if (is_string($path) && $path !== '') {
            $relativePath = ltrim($path, '/\\');

            $candidates = array_values(array_unique([
                base_path($relativePath),
                base_path($path),
                $path,
            ]));

            foreach ($candidates as $candidate) {
                // Suppress warnings for disallowed absolute paths under open_basedir.
                if (! @is_file($candidate)) {
                    continue;
                }

                $decoded = json_decode((string) file_get_contents($candidate), true);

                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        $json = config('services.firebase.service_account_json');

        if (! is_string($json) || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $base64Decoded = base64_decode($json, true);

        if ($base64Decoded === false) {
            return null;
        }

        $decoded = json_decode($base64Decoded, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function logSkipReason(string $message, array $context = []): void
    {
        if ($this->skipReasonLogged) {
            return;
        }

        $this->skipReasonLogged = true;

        Log::warning($message, $context);
    }
}
