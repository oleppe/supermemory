<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Google\Cloud\Translate\V3\Client\TranslationServiceClient;
use Google\Cloud\Translate\V3\TranslateTextRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TranslationController extends Controller
{
    /**
     * Translate an array of texts.
     */
    public function translate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_language' => ['required', 'string', 'max:10'],
            'text' => ['nullable', 'string', 'required_without:texts'],
            'texts' => ['nullable', 'array', 'required_without:text', 'min:1'],
            'texts.*' => ['required', 'string'],
        ]);

        $texts = $this->normalizeTexts($validated);
        $translate = $this->makeTranslationClient();
        $projectId = $this->resolveProjectId();
        $parent = $translate->locationName($projectId, 'global');

        $requestPayload = (new TranslateTextRequest())
            ->setContents($texts)
            ->setTargetLanguageCode($validated['target_language'])
            ->setMimeType('text/plain')
            ->setParent($parent);

        $translations = [];

        try {
            $response = $translate->translateText($requestPayload);

            foreach ($response->getTranslations() as $key => $translation) {
                $translations[$key] = $translation->getTranslatedText();
            }
        } finally {
            $translate->close();
        }

        return response()->json([
            'data' => [
                'translations' => $translations,
                'target_language' => $validated['target_language'],
            ],
        ]);
    }

    private function normalizeTexts(array $validated): array
    {
        if (isset($validated['texts']) && is_array($validated['texts'])) {
            return array_values($validated['texts']);
        }

        return [(string) $validated['text']];
    }

    private function makeTranslationClient(): TranslationServiceClient
    {
        return new TranslationServiceClient([
            'credentials' => $this->resolveCredentialsPath(),
             'transport' => 'rest',
        ]);
    }

    private function resolveCredentialsPath(): string
    {
        $configuredPath = config('services.firebase.service_account_path');
        $candidates = array_values(array_unique(array_filter([
            is_string($configuredPath) ? $configuredPath : null,
            is_string($configuredPath) ? base_path($configuredPath) : null,
            is_string($configuredPath) ? base_path(ltrim($configuredPath, '/\\')) : null,
            base_path('service-account.json'),
        ], fn (mixed $path): bool => is_string($path) && $path !== '')));

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Google Cloud service account credentials were not found.');
    }

    private function resolveProjectId(): string
    {
        $configuredProjectId = config('services.firebase.project_id');

        if (is_string($configuredProjectId) && $configuredProjectId !== '') {
            return $configuredProjectId;
        }

        $decoded = json_decode((string) file_get_contents($this->resolveCredentialsPath()), true);

        if (is_array($decoded) && is_string($decoded['project_id'] ?? null) && $decoded['project_id'] !== '') {
            return $decoded['project_id'];
        }

        throw new RuntimeException('Google Cloud project ID is not configured for translation.');
    }
}
