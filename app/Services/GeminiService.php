<?php

namespace App\Services;

use App\Exceptions\GeminiApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GeminiService
{
    private string $baseUrl;

    private ?string $apiKey;

    private string $model;

    private int $timeout;

    private float $temperature;

    private int $maxOutputTokens;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $this->apiKey = config('services.gemini.api_key');
        $this->model = (string) config('services.gemini.model', 'gemini-2.5-flash');
        $this->timeout = (int) config('services.gemini.timeout', 30);
        $this->temperature = (float) config('services.gemini.temperature', 0.2);
        $this->maxOutputTokens = (int) config('services.gemini.max_output_tokens', 1024);
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey);
    }

    public function model(): string
    {
        return $this->model;
    }

    public function generateAnswer(string $question, array $contextSegments, array $conversationHistory = []): string
    {
        if (! $this->isConfigured()) {
            throw GeminiApiException::notConfigured();
        }

        $context = $this->formatContext($contextSegments);
        $history = $this->formatConversationHistory($conversationHistory);
        $prompt = $this->buildPrompt($question, $context, $history);

        $response = Http::acceptJson()
            ->timeout($this->timeout)
            ->withHeaders([
                'x-goog-api-key' => (string) $this->apiKey,
            ])
            ->post($this->buildUrl('/models/'.$this->model.':generateContent'), [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => $this->temperature,
                    'maxOutputTokens' => $this->maxOutputTokens,
                ],
            ]);

        $payload = $this->handleResponse($response);
        $answer = $this->extractAnswer($payload);

        if ($answer === null) {
            throw new GeminiApiException(
                statusCode: 502,
                detail: $payload,
                message: 'Gemini API returned an empty answer',
            );
        }

        return $answer;
    }

    private function buildPrompt(string $question, string $context, string $conversationHistory): string
    {
        return <<<PROMPT
You are a helpful document Q&A assistant.

Answer the user's latest question using only the provided context.

Rules:
1. Use only facts that appear in the provided context.
2. Do not mention citations, document numbers, or source labels in the final answer.
3. If the context is insufficient, say clearly that you do not have enough information in the provided context.
4. Keep the answer concise, direct, and professional.
5. Do not invent, assume, or fill in missing details.
6. Use the conversation history only to understand references like "it", "that section", or follow-up wording. Do not treat conversation history as factual source material unless the same facts appear in the provided context.

Conversation history:
{$conversationHistory}

Question:
{$question}

Context:
{$context}
PROMPT;
    }

    private function formatConversationHistory(array $conversationHistory, int $maxMessages = 6): string
    {
        $messages = array_slice($conversationHistory, -$maxMessages);
        $lines = [];

        foreach ($messages as $message) {
            if (! is_array($message)) {
                continue;
            }

            $role = $message['role'] ?? null;
            $content = $message['content'] ?? null;

            if (! is_string($role) || ! is_string($content)) {
                continue;
            }

            $normalizedRole = match ($role) {
                'assistant' => 'Assistant',
                default => 'User',
            };

            $normalizedContent = trim(preg_replace('/\s+/', ' ', $content) ?? '');

            if ($normalizedContent === '') {
                continue;
            }

            $lines[] = sprintf('%s: %s', $normalizedRole, $normalizedContent);
        }

        if ($lines === []) {
            return 'None';
        }

        return implode("\n", $lines);
    }

    private function formatContext(array $contextSegments): string
    {
        $lines = [];

        foreach ($contextSegments as $index => $segment) {
            if (! is_string($segment)) {
                continue;
            }

            $normalized = trim(preg_replace('/\s+/', ' ', $segment) ?? '');

            if ($normalized === '') {
                continue;
            }

            $lines[] = sprintf('[%d] %s', $index + 1, $normalized);
        }

        return implode("\n", $lines);
    }

    private function buildUrl(string $path): string
    {
        return $this->baseUrl.$path;
    }

    private function handleResponse(Response $response): array
    {
        if ($response->status() !== 200) {
            $this->throwFromResponse($response);
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

        if (! is_array($detail)) {
            $detail = $response->body();
        }

        $message = 'Gemini API error';

        if (is_array($detail)) {
            $message = (string) ($detail['error']['message'] ?? $detail['message'] ?? $message);
        } elseif (is_string($detail) && $detail !== '') {
            $message = $detail;
        }

        if ($response->status() === 404 && str_contains(strtolower($message), 'model')) {
            $message .= ' Check GEMINI_MODEL and use a model that supports generateContent for the configured API version, such as gemini-2.5-flash.';
        }

        throw new GeminiApiException(
            statusCode: $response->status() ?: 500,
            detail: $detail,
            message: $message,
        );
    }

    private function extractAnswer(array $payload): ?string
    {
        $candidates = $payload['candidates'] ?? [];

        if (! is_array($candidates)) {
            return null;
        }

        foreach ($candidates as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $parts = $candidate['content']['parts'] ?? [];

            if (! is_array($parts)) {
                continue;
            }

            $text = collect($parts)
                ->map(fn (mixed $part): string => is_array($part) && is_string($part['text'] ?? null) ? $part['text'] : '')
                ->implode('');

            $text = trim($text);

            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }
}
