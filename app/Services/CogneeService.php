<?php

namespace App\Services;

use App\Exceptions\CogneeApiException;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CogneeService
{
    private string $baseUrl;
    private string $apiPrefix;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('cognee.base_url'), '/');
        $this->apiPrefix = rtrim(config('cognee.api_prefix'), '/');
    }

    /**
     * Register a new user on Cognee.
     *
     * @return array{id: string, email: string, is_active: bool, is_superuser: bool, is_verified: bool}
     */
    public function register(string $email, string $password): array
    {
        $response = Http::acceptJson()
            ->post($this->buildUrl('/auth/register'), [
                'email' => $email,
                'password' => $password,
            ]);

        return $this->handleResponse($response, 201);
    }

    /**
     * Login to Cognee and return the auth token from the Set-Cookie header.
     *
     * @return array{token: string, response: array}
     */
    public function login(string $email, string $password): array
    {
        $response = Http::asForm()
            ->withOptions(['allow_redirects' => false])
            ->post($this->buildUrl('/auth/login'), [
                'username' => $email,
                'password' => $password,
            ]);

        if ($response->status() !== 200 && $response->status() !== 204) {
            $this->throwFromResponse($response);
        }

        $token = $this->extractAuthToken($response);

        return [
            'token' => $token,
            'response' => $response->json() ?? [],
        ];
    }

    /**
     * Get the current Cognee user profile.
     */
    public function me(string $cogneeToken): array
    {
        $response = Http::acceptJson()
            ->withHeaders($this->authHeaders($cogneeToken))
            ->get($this->buildUrl('/auth/me'));

        return $this->handleResponse($response);
    }

    /**
     * Logout from Cognee.
     */
    public function logout(string $cogneeToken): void
    {
        $response = $this->authenticatedRequest($cogneeToken)
            ->post($this->buildUrl('/auth/logout'));

        $this->assertSuccessful($response, [200, 204]);
    }

    public function getDatasets(string $cogneeToken): array
    {
        $response = $this->authenticatedRequest($cogneeToken)
            ->get($this->buildUrl('/datasets'));

        return $this->handleResponse($response);
    }

    public function createDataset(string $cogneeToken, string $name): array
    {
        $response = $this->authenticatedRequest($cogneeToken)
            ->post($this->buildUrl('/datasets'), [
                'name' => $name,
            ]);

        return $this->handleResponse($response);
    }

    public function getDatasetStatus(string $cogneeToken, array $datasetIds = []): array
    {
        $response = $this->authenticatedRequest($cogneeToken)
            ->get($this->buildUrl('/datasets/status'), [
                'dataset' => $datasetIds,
            ]);

        return $this->handleResponse($response);
    }

    public function addData(
        string $cogneeToken,
        string $data,
        ?string $datasetId = null,
        ?string $datasetName = null,
        array $nodeSet = [],
        ?string $filename = null,
    ): array {
        $uploadFilename = $this->buildUploadFilename($filename, $data);

        $response = $this->authenticatedRequest($cogneeToken)
            ->asMultipart()
            ->attach('data', $data, $uploadFilename, [
                'Content-Type' => 'text/plain',
            ])
            ->post($this->buildUrl('/add'), array_filter([
                'datasetId' => $datasetId,
                'datasetName' => $datasetName,
                'node_set' => $nodeSet === [] ? null : $nodeSet,
            ], static fn (mixed $value) => $value !== null));

        return $this->handleResponse($response);
    }

    public function cognify(
        string $cogneeToken,
        ?string $datasetId = null,
        ?string $datasetName = null,
        bool $runInBackground = false,
        ?string $customPrompt = null,
        array $ontologyKey = [],
    ): array {
        $payload = [
            'runInBackground' => $runInBackground,
        ];

        if ($datasetId !== null) {
            $payload['datasetIds'] = [$datasetId];
        }

        if ($datasetName !== null) {
            $payload['datasets'] = [$datasetName];
        }

        if ($customPrompt !== null) {
            $payload['customPrompt'] = $customPrompt;
        }

        if ($ontologyKey !== []) {
            $payload['ontologyKey'] = $ontologyKey;
        }

        $response = $this->authenticatedRequest($cogneeToken)
            ->post($this->buildUrl('/cognify'), $payload);

        return $this->handleResponse($response);
    }

    public function memify(
        string $cogneeToken,
        ?string $datasetId = null,
        ?string $datasetName = null,
        array $extractionTasks = [],
        array $enrichmentTasks = [],
        ?string $data = null,
        array $nodeName = [],
        bool $runInBackground = false,
    ): array {
        $payload = [
            'runInBackground' => $runInBackground,
        ];

        if ($datasetId !== null) {
            $payload['datasetId'] = $datasetId;
        }

        if ($datasetName !== null) {
            $payload['datasetName'] = $datasetName;
        }

        if ($extractionTasks !== []) {
            $payload['extractionTasks'] = $extractionTasks;
        }

        if ($enrichmentTasks !== []) {
            $payload['enrichmentTasks'] = $enrichmentTasks;
        }

        if ($data !== null) {
            $payload['data'] = $data;
        }

        if ($nodeName !== []) {
            $payload['nodeName'] = $nodeName;
        }

        $response = $this->authenticatedRequest($cogneeToken)
            ->post($this->buildUrl('/memify'), $payload);

        return $this->handleResponse($response);
    }

    public function search(
        string $cogneeToken,
        string $query,
        ?string $datasetId = null,
        ?string $datasetName = null,
        string $searchType = 'GRAPH_COMPLETION',
        int $topK = 10,
        bool $onlyContext = false,
        ?string $systemPrompt = null,
        array $nodeNames = [],
    ): array {
        $payload = [
            'query' => $query,
            'searchType' => $searchType,
            'topK' => $topK,
            'onlyContext' => $onlyContext,
        ];

        if ($datasetId !== null) {
            $payload['datasetIds'] = [$datasetId];
        }

        if ($datasetName !== null) {
            $payload['datasets'] = [$datasetName];
        }

        if ($systemPrompt !== null) {
            $payload['systemPrompt'] = $systemPrompt;
        }

        if ($nodeNames !== []) {
            $payload['nodeName'] = $nodeNames;
        }

        $response = $this->authenticatedRequest($cogneeToken)
            ->post($this->buildUrl('/search'), $payload);

        return $this->handleResponse($response);
    }

    public function getSearchHistory(string $cogneeToken): array
    {
        $response = $this->authenticatedRequest($cogneeToken)
            ->get($this->buildUrl('/search'));

        return $this->handleResponse($response);
    }

    public function checkConnection(string $cogneeToken): array
    {
        $response = $this->authenticatedRequest($cogneeToken)
            ->post($this->buildUrl('/checks/connection'));

        return $this->handleResponse($response);
    }

    public function health(): array
    {
        $response = Http::acceptJson()->get($this->baseUrl.'/health');

        return $this->handleResponse($response);
    }

    public function detailedHealth(): array
    {
        $response = Http::acceptJson()->get($this->baseUrl.'/health/detailed');

        return $this->handleResponse($response);
    }

    public function restoreUserSession(User $user): string
    {
        if (! $user->cognee_password) {
            throw CogneeApiException::sessionRecoveryUnavailable();
        }

        return $this->authenticateUser($user, $user->cognee_password, false);
    }

    public function authenticateUser(User $user, string $password, bool $rememberPassword = true): string
    {
        $loginResult = $this->login($user->email, $password);

        $attributes = [
            'cognee_token' => $loginResult['token'],
        ];

        if ($rememberPassword) {
            $attributes['cognee_password'] = $password;
        }

        $user->forceFill($attributes)->save();

        return $loginResult['token'];
    }

    private function buildUrl(string $path): string
    {
        return $this->baseUrl . $this->apiPrefix . $path;
    }

    private function buildUploadFilename(?string $filename, string $data): string
    {
        $contentHash = substr(hash('sha256', $data), 0, 12);

        if ($filename === null || trim($filename) === '') {
            return "document-{$contentHash}.txt";
        }

        $info = pathinfo(basename($filename));
        $baseName = $info['filename'] ?? 'document';
        $extension = isset($info['extension']) && $info['extension'] !== ''
            ? '.'.$info['extension']
            : '';

        $safeBaseName = Str::of($baseName)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '-')
            ->trim('-_')
            ->value();

        if ($safeBaseName === '') {
            $safeBaseName = 'document';
        }

        return "{$safeBaseName}-{$contentHash}{$extension}";
    }

    private function authHeaders(string $cogneeToken): array
    {
        return [
            'Cookie' => 'auth_token=' . $cogneeToken,
        ];
    }

    private function authenticatedRequest(string $cogneeToken): PendingRequest
    {
        return Http::acceptJson()->withHeaders($this->authHeaders($cogneeToken));
    }

    private function extractAuthToken(Response $response): string
    {
        $cookies = $response->cookies();

        $token = $cookies->getCookieByName('auth_token');

        if ($token) {
            return $token->getValue();
        }

        // Fallback: parse Set-Cookie header manually
        $setCookieHeaders = $response->header('Set-Cookie');
        if ($setCookieHeaders && preg_match('/auth_token=([^;]+)/', $setCookieHeaders, $matches)) {
            return $matches[1];
        }

        throw new CogneeApiException(
            statusCode: 502,
            cogneeDetail: 'No auth_token cookie in Cognee login response',
            message: 'Failed to authenticate with Cognee',
        );
    }

    private function handleResponse(Response $response, int|array $expectedStatus = 200): array
    {
        $this->assertSuccessful($response, $expectedStatus);

        if ($response->status() === 204 || trim($response->body()) === '') {
            return [];
        }

        $decoded = $response->json();

        if (is_array($decoded)) {
            return $decoded;
        }

        return ['body' => $response->body()];
    }

    private function assertSuccessful(Response $response, int|array $expectedStatus): void
    {
        $expectedStatuses = is_array($expectedStatus) ? $expectedStatus : [$expectedStatus];

        if (! in_array($response->status(), $expectedStatuses, true)) {
            $this->throwFromResponse($response);
        }
    }

    private function throwFromResponse(Response $response): never
    {
        $body = $response->json();
        $detail = $body['detail'] ?? $body ?? $response->body();

        if ($response->status() === 401) {
            throw CogneeApiException::sessionExpired();
        }

        throw new CogneeApiException(
            statusCode: $response->status(),
            cogneeDetail: $detail,
            message: is_string($detail) ? $detail : 'Cognee API error',
        );
    }
}
