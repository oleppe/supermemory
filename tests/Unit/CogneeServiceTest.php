<?php

namespace Tests\Unit;

use App\Services\CogneeService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CogneeServiceTest extends TestCase
{
    public function test_add_data_uses_hash_based_default_filename(): void
    {
        config()->set('cognee.base_url', 'http://localhost:8000');
        config()->set('cognee.api_prefix', '/api/v1');

        Http::fake([
            'http://localhost:8000/api/v1/add' => Http::response(['status' => 'added'], 200),
        ]);

        $service = new CogneeService();
        $payload = 'Hello Cognee';
        $expectedFilename = 'document-'.substr(hash('sha256', $payload), 0, 12).'.txt';

        $result = $service->addData('cognee-token', $payload, 'dataset-1', 'Docs');

        Http::assertSent(function ($request) use ($expectedFilename) {
            return $request->url() === 'http://localhost:8000/api/v1/add'
                && $request->hasHeader('Cookie', 'auth_token=cognee-token')
                && str_contains($request->body(), 'filename="'.$expectedFilename.'"')
                && str_contains($request->body(), 'Hello Cognee');
        });

        $this->assertSame(['status' => 'added'], $result);
    }

    public function test_add_data_appends_hash_to_original_filename(): void
    {
        config()->set('cognee.base_url', 'http://localhost:8000');
        config()->set('cognee.api_prefix', '/api/v1');

        Http::fake([
            'http://localhost:8000/api/v1/add' => Http::response(['status' => 'added'], 200),
        ]);

        $service = new CogneeService();
        $payload = 'Updated content';
        $expectedFilename = 'report-v2-'.substr(hash('sha256', $payload), 0, 12).'.md';

        $result = $service->addData('cognee-token', $payload, 'dataset-1', 'Docs', [], 'report v2.md');

        Http::assertSent(function ($request) use ($expectedFilename) {
            return $request->url() === 'http://localhost:8000/api/v1/add'
                && str_contains($request->body(), 'filename="'.$expectedFilename.'"')
                && str_contains($request->body(), 'Updated content');
        });

        $this->assertSame(['status' => 'added'], $result);
    }

    public function test_cognify_uses_requested_background_flag(): void
    {
        config()->set('cognee.base_url', 'http://localhost:8000');
        config()->set('cognee.api_prefix', '/api/v1');

        Http::fake([
            'http://localhost:8000/api/v1/cognify' => Http::response(['pipeline_run_id' => 'run-1'], 200),
        ]);

        $service = new CogneeService();

        $result = $service->cognify(
            'cognee-token',
            '123e4567-e89b-12d3-a456-426614174000',
            null,
            true,
            'Extract relations',
            ['schema'],
        );

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:8000/api/v1/cognify'
                && $request->hasHeader('Cookie', 'auth_token=cognee-token')
                && $request['runInBackground'] === true
                && $request['datasetIds'] === ['123e4567-e89b-12d3-a456-426614174000']
                && $request['customPrompt'] === 'Extract relations'
                && $request['ontologyKey'] === ['schema'];
        });

        $this->assertSame(['pipeline_run_id' => 'run-1'], $result);
    }

    public function test_memify_maps_request_fields_to_upstream_payload(): void
    {
        config()->set('cognee.base_url', 'http://localhost:8000');
        config()->set('cognee.api_prefix', '/api/v1');

        Http::fake([
            'http://localhost:8000/api/v1/memify' => Http::response(['pipeline_run_id' => 'run-2'], 200),
        ]);

        $service = new CogneeService();

        $result = $service->memify(
            'cognee-token',
            null,
            'Docs',
            ['ExtractTopics'],
            ['SummarizeCommunities'],
            'Direct text input',
            ['Project Phoenix'],
            true,
        );

        Http::assertSent(function ($request) {
            return $request->url() === 'http://localhost:8000/api/v1/memify'
                && $request->hasHeader('Cookie', 'auth_token=cognee-token')
                && $request['datasetName'] === 'Docs'
                && $request['extractionTasks'] === ['ExtractTopics']
                && $request['enrichmentTasks'] === ['SummarizeCommunities']
                && $request['data'] === 'Direct text input'
                && $request['nodeName'] === ['Project Phoenix']
                && $request['runInBackground'] === true;
        });

        $this->assertSame(['pipeline_run_id' => 'run-2'], $result);
    }
}
