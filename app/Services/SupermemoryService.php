<?php

namespace App\Services;

use App\Exceptions\SupermemoryApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class SupermemoryService
{
    private string $baseUrl;

    private ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('supermemory.base_url', 'https://api.supermemory.ai'), '/');
        $this->apiKey = config('supermemory.api_key');
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    public function addMemory(
        string $content,
        string $containerTag,
        array $metadata = [],
        ?string $customId = null,
        ?string $entityContext = null,
    ): array {
        $normalizedMetadata = $this->normalizeMetadata($metadata);

        if ($customId !== null) {
            $normalizedMetadata['custom_id'] = $customId;
        }

        if ($entityContext !== null) {
            $normalizedMetadata['entity_context'] = $entityContext;
        }

        $memory = [
            'content' => $content,
            'isStatic' => false,
        ];

        if ($normalizedMetadata !== []) {
            $memory['metadata'] = $normalizedMetadata;
        }

        $payload = [
            'memories' => [$memory],
            'containerTag' => $containerTag,
        ];

        $response = $this->authenticatedJsonRequest()
            ->post($this->buildUrl('/v4/memories'), $payload);

        $result = $this->handleResponse($response, [200, 201]);

        $firstMemory = $result['memories'][0] ?? null;

        if (is_array($firstMemory)) {
            $result['id'] = $firstMemory['id'] ?? $firstMemory['memoryId'] ?? null;
            $result['status'] = $firstMemory['status'] ?? 'processed';
        }

        return $result;
    }

    public function uploadFile(
        UploadedFile $file,
        string $containerTag,
        array $metadata = [],
        ?string $customId = null,
        ?string $entityContext = null,
    ): array {
        $payload = [
            'containerTags' => $containerTag,
        ];

        if ($customId !== null) {
            $payload['customId'] = $customId;
        }

        if ($entityContext !== null) {
            $payload['entityContext'] = $entityContext;
        }

        if ($metadata !== []) {
            $payload['metadata'] = json_encode($this->normalizeMetadata($metadata), JSON_THROW_ON_ERROR);
        }

        $response = $this->authenticatedRequest()
            ->asMultipart()
            ->attach(
                'file',
                (string) file_get_contents($file->getRealPath()),
                $file->getClientOriginalName(),
                ['Content-Type' => $file->getMimeType() ?: 'application/octet-stream'],
            )
            ->post($this->buildUrl('/v3/documents/file'), $payload);

        return $this->handleResponse($response, [200, 201]);
    }

    public function getDocument(string $documentId): array
    {
        $response = $this->authenticatedRequest()
            ->get($this->buildUrl('/v3/documents/'.$documentId));

        return $this->handleResponse($response);
    }

    public function listDocuments(
        ?string $containerTag = null,
        int $page = 1,
        int $limit = 10,
        string $sort = 'createdAt',
        string $order = 'desc',
    ): array {
        $payload = [
            'page' => $page,
            'limit' => $limit,
            'sort' => $sort,
            'order' => $order,
        ];

        if ($containerTag !== null) {
            $payload['containerTags'] = [$containerTag];
        }

        $response = $this->authenticatedJsonRequest()
            ->post($this->buildUrl('/v3/documents/list'), $payload);

        return $this->handleResponse($response);
    }

    public function searchMemories(
        string $query,
        string $containerTag,
        int $limit = 10,
        ?float $threshold = null,
        bool $rerank = false,
    ): array {
        $payload = [
            'q' => $query,
            'containerTag' => $containerTag,
            'limit' => $limit,
            'rerank' => $rerank,
            'searchMode' => 'hybrid',
        ];

        if ($threshold !== null) {
            $payload['threshold'] = $threshold;
        }

        $response = $this->authenticatedJsonRequest()
            ->post($this->buildUrl('/v4/search'), $payload);

        return $this->handleResponse($response);
    }

    public function searchDocuments(
        string $query,
        string $containerTag,
        int $limit = 10,
        ?float $threshold = null,
        bool $rerank = false,
    ): array {
        $payload = [
            'q' => $query,
            'containerTag' => $containerTag,
            'limit' => $limit,
            'rerank' => $rerank,
            'searchMode' => 'hybrid',
            'includeSummary' => true,
            'includeFullDocs' => true,
            'onlyMatchingChunks' => true,
        ];

        if ($threshold !== null) {
            $payload['threshold'] = $threshold;
        }

        $response = $this->authenticatedJsonRequest()
            ->post($this->buildUrl('/v4/search'), $payload);

        return $this->handleResponse($response);
    }

    public function health(): array
    {
        return $this->listDocuments(limit: 1);
    }

    public function checkConnection(string $containerTag): array
    {
        $result = $this->listDocuments($containerTag, 1, 1);
        $documents = $result['documents'] ?? $result['memories'] ?? [];

        return [
            'connected' => true,
            'container_tag' => $containerTag,
            'document_count' => count($documents),
        ];
    }

    private function authenticatedRequest(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw SupermemoryApiException::notConfigured();
        }

        return Http::acceptJson()
            ->withToken((string) $this->apiKey)
            ->timeout((int) config('supermemory.timeout', 30));
    }

    private function authenticatedJsonRequest(): PendingRequest
    {
        return $this->authenticatedRequest()->asJson();
    }

    private function buildUrl(string $path): string
    {
        return $this->baseUrl.$path;
    }

    private function handleResponse(Response $response, array $expectedStatusCodes = [200]): array
    {
        if (! in_array($response->status(), $expectedStatusCodes, true)) {
            $this->throwFromResponse($response);
        }

        if ($response->status() === 204 || trim($response->body()) === '') {
            return [];
        }

        $payload = $response->json();

        if (is_array($payload)) {
            return $payload;
        }

        return ['body' => $response->body()];
    }

    private function throwFromResponse(Response $response): never
    {
        $detail = $response->json();

        if ($detail === null || $detail === []) {
            $detail = $response->body();
        }

        $message = 'Supermemory API error';

        if (is_array($detail) && is_string($detail['message'] ?? null)) {
            $message = $detail['message'];
        } elseif (is_string($detail) && $detail !== '') {
            $message = $detail;
        }

        throw new SupermemoryApiException(
            statusCode: $response->status() ?: 500,
            detail: $detail,
            message: $message,
        );
    }

    private function normalizeMetadata(array $metadata): array
    {
        return array_filter(
            $metadata,
            static fn (mixed $value): bool => is_string($value) || is_int($value) || is_float($value) || is_bool($value),
        );
    }
}
