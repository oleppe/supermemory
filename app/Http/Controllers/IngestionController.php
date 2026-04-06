<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddFilesRequest;
use App\Http\Requests\AddTextRequest;
use App\Services\CogneeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class IngestionController extends Controller
{
    public function __construct(
        private readonly CogneeService $cogneeService,
    ) {}

    public function storeText(AddTextRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();
        [$dataset, $result, $cognify] = $this->withCogneeSession($user, $this->cogneeService, function (string $token) use ($validated) {
            $dataset = $this->resolveDataset($token, $validated['dataset_id'] ?? null, $validated['dataset_name'] ?? null);

            $result = $this->cogneeService->addData(
                $token,
                $validated['text'],
                $dataset['id'] ?? null,
                $dataset['name'] ?? null,
                $validated['node_set'] ?? [],
                null,
            );

            $cognify = $this->triggerCognifyAfterIngestion($token, $dataset, $validated);

            return [$dataset, $result, $cognify];
        });

        return response()->json([
            'dataset' => $dataset,
            'result' => $result,
            'cognify' => $cognify,
        ], 201);
    }

    public function storeFiles(AddFilesRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $validated = $request->validated();
        $files = collect($request->file('files'))
            ->filter(fn (mixed $file): bool => $file instanceof UploadedFile)
            ->values()
            ->all();

        [$dataset, $result, $cognify] = $this->withCogneeSession($user, $this->cogneeService, function (string $token) use ($validated, $files) {
            $dataset = $this->resolveDataset($token, $validated['dataset_id'] ?? null, $validated['dataset_name'] ?? null);

            $results = collect($files)
                ->map(fn (UploadedFile $file): array => $this->cogneeService->addData(
                    $token,
                    (string) file_get_contents($file->getRealPath()),
                    $dataset['id'] ?? null,
                    $dataset['name'] ?? null,
                    $validated['node_set'] ?? [],
                    $file->getClientOriginalName(),
                ))
                ->values();

            $result = $results->count() === 1
                ? $results->first()
                : $results->all();

            $cognify = $this->triggerCognifyAfterIngestion($token, $dataset, $validated);

            return [$dataset, $result, $cognify];
        });

        return response()->json([
            'dataset' => $dataset,
            'result' => $result,
            'cognify' => $cognify,
        ], 201);
    }

    private function resolveDataset(string $token, ?string $datasetId, ?string $datasetName): array
    {
        if ($datasetName !== null) {
            return $this->cogneeService->createDataset($token, $datasetName);
        }

        return ['id' => $datasetId];
    }

    private function triggerCognifyAfterIngestion(string $token, array $dataset, array $validated): array
    {
        $shouldRunCognify = $validated['run_cognify'] ?? true;

        if (! $shouldRunCognify) {
            return [
                'triggered' => false,
                'reason' => 'disabled',
            ];
        }

        $result = $this->cogneeService->cognify(
            $token,
            $dataset['id'] ?? ($validated['dataset_id'] ?? null),
            $dataset['name'] ?? ($validated['dataset_name'] ?? null),
            $validated['run_in_background'] ?? true,
            $validated['custom_prompt'] ?? null,
            $validated['ontology_key'] ?? [],
        );

        return [
            'triggered' => true,
            'result' => $result,
        ];
    }
}
