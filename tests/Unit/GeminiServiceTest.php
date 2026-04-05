<?php

namespace Tests\Unit;

use App\Exceptions\GeminiApiException;
use App\Services\GeminiService;
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
}
