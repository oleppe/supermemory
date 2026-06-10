<?php

namespace Tests\Unit;

use App\Exceptions\GeminiApiException;
use App\Services\GeminiService;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    public function test_generate_answer_sends_expected_payload_and_returns_text(): void
    {
        config()->set('services.gemini.api_key', 'gemini-key');
        config()->set('services.gemini.model', 'gemini-2.5-flash');
        config()->set('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        config()->set('services.gemini.timeout', 30);
        config()->set('services.gemini.temperature', 0.2);
        config()->set('services.gemini.max_output_tokens', 512);

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Answer from Gemini'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = new GeminiService;
        $answer = $service->generateAnswer('What does it say?', [
            '[Memory] The agreement renews automatically.',
            '[Document Chunk] Notice must be sent before renewal.',
        ], [
            ['role' => 'user', 'content' => 'What document is this?'],
            ['role' => 'assistant', 'content' => 'It is the agreement.'],
        ]);

        Http::assertSent(function ($request) {
            $prompt = $request['contents'][0]['parts'][0]['text'] ?? '';

            return $request->url() === 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent'
                && $request->hasHeader('x-goog-api-key', 'gemini-key')
                && str_contains($prompt, 'What does it say?')
                && str_contains($prompt, 'The agreement renews automatically')
                && str_contains($prompt, 'Conversation history:')
                && str_contains($prompt, 'User: What document is this?')
                && str_contains($prompt, 'Assistant: It is the agreement.')
                && $request['generationConfig']['temperature'] === 0.2
                && $request['generationConfig']['maxOutputTokens'] === 512;
        });

        $this->assertSame('Answer from Gemini', $answer);
    }

    public function test_generate_answer_throws_when_not_configured(): void
    {
        config()->set('services.gemini.api_key', null);

        $service = new GeminiService;

        $this->expectException(GeminiApiException::class);
        $this->expectExceptionMessage('Gemini API is not configured');

        $service->generateAnswer('What?', ['Context']);
    }

    public function test_generate_answer_throws_on_upstream_error_response(): void
    {
        config()->set('services.gemini.api_key', 'bad-key');
        config()->set('services.gemini.model', 'gemini-2.5-flash');
        config()->set('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent' => Http::response([
                'error' => [
                    'message' => 'API key not valid.',
                ],
            ], 401),
        ]);

        $service = new GeminiService;

        $this->expectException(GeminiApiException::class);
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('API key not valid.');

        $service->generateAnswer('What?', ['Context']);
    }

    public function test_analyze_document_images_sends_inline_images_and_normalizes_response(): void
    {
        Config::set('services.gemini.api_key', 'test-key');
        Config::set('services.gemini.model', 'gemini-2.5-flash');
        Config::set('services.gemini.base_url', 'https://example.test/v1beta');
        Config::set('services.gemini.timeout', 30);
        Config::set('services.gemini.max_output_tokens', 1024);

        Http::fake([
            'https://example.test/v1beta/models/gemini-2.5-flash:generateContent' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'document_type' => 'receipt',
                                        'category' => 'Expenses',
                                        'extracted_text' => 'Acme Cafe receipt',
                                        'summary' => 'Restaurant receipt for lunch.',
                                        'action_items' => ['Submit expense report', 'Submit expense report'],
                                        'entities' => [
                                            'Organization' => 'Acme Cafe',
                                            'Date' => '2026-04-07',
                                            'Currency' => 'USD',
                                            'Person' => '',
                                        ],
                                        'amount' => '42.50',
                                        'date' => '2026-04-07',
                                        'merchant' => 'Acme Cafe',
                                        'payment_method' => '',
                                        'items' => ['Lunch combo', 'Lunch combo', 'Coffee'],
                                        'tax_amount' => '3.50',
                                        'tip_amount' => 'null',
                                    ], JSON_THROW_ON_ERROR),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = $this->makeGeminiService();
        $pages = [
            UploadedFile::fake()->image('page-1.jpg'),
            UploadedFile::fake()->image('page-2.png'),
        ];

        $result = $service->analyzeDocumentImages($pages, ['expenses', 'travel']);

        $this->assertSame('receipt', $result['document_type']);
        $this->assertSame('expenses', $result['category']);
        $this->assertSame('Acme Cafe receipt', $result['extracted_text']);
        $this->assertSame('Restaurant receipt for lunch.', $result['summary']);
        $this->assertSame(['Submit expense report'], $result['action_items']);
        $this->assertSame([
            'Organization' => 'Acme Cafe',
            'Date' => '2026-04-07',
            'Currency' => 'USD',
        ], $result['entities']);
        $this->assertSame(42.5, $result['amount']);
        $this->assertNull($result['tip_amount']);
        $this->assertSame('N/A', $result['payment_method']);
        $this->assertSame(['Lunch combo', 'Coffee'], $result['items']);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $parts = $payload['contents'][0]['parts'] ?? [];

            return $request->url() === 'https://example.test/v1beta/models/gemini-2.5-flash:generateContent'
                && $request->hasHeader('x-goog-api-key', 'test-key')
                && ($payload['generationConfig']['responseMimeType'] ?? null) === 'application/json'
                && ($payload['generationConfig']['maxOutputTokens'] ?? null) === 4096
                && count($parts) === 3
                && is_string($parts[0]['text'] ?? null)
                && str_contains($parts[0]['text'] ?? '', 'Analyze these document page images (2 page(s)) as a single document.')
                && str_contains($parts[0]['text'] ?? '', 'Do not include person names in the "entities" object')
                && ($parts[1]['inline_data']['mime_type'] ?? null) === 'image/jpeg'
                && ($parts[2]['inline_data']['mime_type'] ?? null) === 'image/png'
                && filled($parts[1]['inline_data']['data'] ?? null)
                && filled($parts[2]['inline_data']['data'] ?? null);
        });
    }

    public function test_analyze_document_images_adds_fallback_action_items_and_normalizes_entity_aliases(): void
    {
        Config::set('services.gemini.api_key', 'test-key');
        Config::set('services.gemini.model', 'gemini-2.5-flash');
        Config::set('services.gemini.base_url', 'https://example.test/v1beta');

        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'document_type' => 'personal',
                                        'category' => 'identity',
                                        'extracted_text' => 'Passport\nName: Jane Doe\nIssuing Authority: Republic Office',
                                        'summary' => 'Passport document for Jane Doe.',
                                        'action_items' => [],
                                        'entities' => [
                                            'name' => 'Jane Doe',
                                            'issuer' => 'Republic Office',
                                        ],
                                        'amount' => 0,
                                        'date' => '2026-04-07',
                                        'merchant' => null,
                                        'payment_method' => 'N/A',
                                        'items' => [],
                                        'tax_amount' => null,
                                        'tip_amount' => null,
                                    ], JSON_THROW_ON_ERROR),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = $this->makeGeminiService();

        $result = $service->analyzeDocumentImages([
            UploadedFile::fake()->image('passport-page.jpg'),
        ], ['identity', 'travel']);

        $this->assertSame('personal', $result['document_type']);
        $this->assertSame('identity', $result['category']);
        $this->assertSame(['Review the extracted personal details and store this document securely'], $result['action_items']);
        $this->assertSame([
            'Organization' => 'Republic Office',
        ], $result['entities']);
    }

    public function test_analyze_document_images_adds_language_instructions_when_user_preference_exists(): void
    {
        Config::set('services.gemini.api_key', 'test-key');
        Config::set('services.gemini.model', 'gemini-2.5-flash');
        Config::set('services.gemini.base_url', 'https://example.test/v1beta');

        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'document_type' => 'receipt',
                                        'category' => 'expenses',
                                        'extracted_text' => 'Receipt text',
                                        'summary' => 'Resume du recu.',
                                        'action_items' => ['Soumettre la note de frais'],
                                        'entities' => [
                                            'Organization' => 'Acme Cafe',
                                        ],
                                        'amount' => 42.5,
                                        'date' => '2026-04-07',
                                        'merchant' => 'Acme Cafe',
                                        'payment_method' => 'Visa',
                                        'items' => ['Lunch combo'],
                                        'tax_amount' => '3.50',
                                        'tip_amount' => null,
                                    ], JSON_THROW_ON_ERROR),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = $this->makeGeminiService();

        $result = $service->analyzeDocumentImages([
            UploadedFile::fake()->image('page-1.jpg'),
        ], ['expenses'], 'fr');

        $this->assertSame('Resume du recu.', $result['summary']);
        $this->assertSame(['Soumettre la note de frais'], $result['action_items']);

        Http::assertSent(function (Request $request): bool {
            $parts = $request->data()['contents'][0]['parts'] ?? [];
            $prompt = $parts[0]['text'] ?? '';

            return is_string($prompt)
                && str_contains($prompt, 'Return "summary", every entry in "action_items", and user-facing "entities" text in fr')
                && str_contains($prompt, 'Keep the entity object keys EXACTLY as: "Organization", "Date", "Currency", "Address"');
        });
    }

    public function test_analyze_document_images_throws_when_gemini_returns_invalid_category(): void
    {
        Config::set('services.gemini.api_key', 'test-key');
        Config::set('services.gemini.model', 'gemini-2.5-flash');
        Config::set('services.gemini.base_url', 'https://example.test/v1beta');

        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => '{"document_type":"receipt","category":"wrong","extracted_text":"x","summary":"y","action_items":[],"entities":{},"amount":1,"date":null,"merchant":null,"payment_method":"N/A","items":[],"tax_amount":null,"tip_amount":null}',
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $service = $this->makeGeminiService();

        $this->expectException(GeminiApiException::class);
        $this->expectExceptionMessage('Gemini API returned a category outside the allowed set.');

        $service->analyzeDocumentImages([
            UploadedFile::fake()->image('page-1.jpg'),
        ], ['expenses']);
    }

    public function test_analyze_document_images_falls_back_from_octet_stream_to_extension_mime(): void
    {
        Config::set('services.gemini.api_key', 'test-key');
        Config::set('services.gemini.model', 'gemini-2.5-flash');
        Config::set('services.gemini.base_url', 'https://example.test/v1beta');

        Http::fake([
            '*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => '{"document_type":"receipt","category":"expenses","extracted_text":"x","summary":"y","action_items":[],"entities":{},"amount":1,"date":null,"merchant":null,"payment_method":"N/A","items":[],"tax_amount":null,"tip_amount":null}',
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $page = UploadedFile::fake()->create('page-1.jpg', 10, 'application/octet-stream');
        $service = $this->makeGeminiService();

        $service->analyzeDocumentImages([$page], ['expenses']);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return ($payload['contents'][0]['parts'][1]['inline_data']['mime_type'] ?? null) === 'image/jpeg';
        });
    }

    private function makeGeminiService(): GeminiService
    {
        $this->app->forgetInstance(GeminiService::class);

        return $this->app->make(GeminiService::class);
    }
}
