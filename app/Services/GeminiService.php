<?php

namespace App\Services;

use App\Exceptions\GeminiApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use JsonException;

class GeminiService
{
    private const SUPPORTED_INLINE_MIME_TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
    ];

    private const DOCUMENT_TYPES = [
        'invoice',
        'receipt',
        'contract',
        'letter',
        'personal',
        'business',
        'other',
    ];

    private const ENTITY_KEYS = [
        'Organization',
        'Date',
        'Currency',
        'Address',
    ];

    private const ENTITY_ALIASES = [
        'Organization' => ['Organization', 'organisation', 'issuer', 'company', 'merchant', 'vendor', 'sender'],
        'Date' => ['Date', 'date', 'document_date', 'due_date'],
        'Currency' => ['Currency', 'currency', 'currency_code'],
        'Address' => ['Address', 'address', 'location', 'billing_address', 'shipping_address'],
    ];

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

    public function analyzeDocumentImages(array $pages, array $allowedCategories, ?string $preferredLanguage = null): array
    {
        if (! $this->isConfigured()) {
            throw GeminiApiException::notConfigured();
        }

        $normalizedCategories = $this->normalizeAllowedCategories($allowedCategories);

        if ($normalizedCategories === []) {
            throw GeminiApiException::malformedResponse(
                message: 'At least one allowed category is required for OCR analysis.',
                detail: ['allowed_categories' => $allowedCategories],
            );
        }

        $response = Http::acceptJson()
            ->timeout($this->timeout)
            ->withHeaders([
                'x-goog-api-key' => (string) $this->apiKey,
            ])
            ->post($this->buildUrl('/models/'.$this->model.':generateContent'), [
                'contents' => [
                    [
                        'parts' => $this->buildDocumentOcrParts($pages, $normalizedCategories, $preferredLanguage),
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0,
                    'maxOutputTokens' => max($this->maxOutputTokens, 4096),
                    'responseMimeType' => 'application/json',
                ],
            ]);

        $payload = $this->handleResponse($response);
        $answer = $this->extractAnswer($payload);

        if ($answer === null) {
            throw GeminiApiException::malformedResponse(
                detail: $payload,
                message: 'Gemini API returned an empty OCR response',
            );
        }

        return $this->normalizeOcrPayload(
            $this->decodeJsonResponse($answer),
            $normalizedCategories,
        );
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
7. The answer should be markdown formatted.
8. If a source filename is present in context (for example "receipt_1775657557025.pdf"), reference it as a markdown link using this exact scheme: [filename](app-file://url-encoded-filename).
9. Only include app-file links for filenames that appear in the provided context.
Conversation history:
{$conversationHistory}

Question:
{$question}

Context:
{$context}
PROMPT;
    }

    private function buildDocumentOcrPrompt(int $pageCount, array $allowedCategories, ?string $preferredLanguage = null): string
    {
        $categoryList = implode('|', $allowedCategories);
        $quotedCategories = implode(', ', array_map(
            fn (string $category): string => '"'.$category.'"',
            $allowedCategories,
        ));
        $languageInstructions = '';

        if (is_string($preferredLanguage) && trim($preferredLanguage) !== '') {
            $language = trim($preferredLanguage);
            $languageInstructions = <<<TEXT

LANGUAGE RULES:
- Return "summary", every entry in "action_items", and user-facing "entities" text in {$language}
- Keep the entity object keys EXACTLY as: "Organization", "Date", "Currency", "Address"
- Do not include a "Person" entity
- Do not invent translations for facts that are not present in the document
- Preserve literal proper names, IDs, exact addresses, account numbers, and raw OCR text as written in the document when translation would alter the original fact
TEXT;
        }

        $identityRule = in_array('identity', $allowedCategories, true)
            ? "\n- For identity cards, passports, driver's licenses, and similar personal records: extract issuing organization, dates, address, and practical follow-ups, but do not include person names in entities"
            : '';

        return <<<PROMPT
Analyze these document page images ({$pageCount} page(s)) as a single document. Determine what type of document it is and extract all relevant information across all pages.

Your response MUST be valid JSON following EXACTLY this format:
{
  "document_type": "invoice|receipt|contract|letter|personal|business|other",
  "category": "{$categoryList}",
  "extracted_text": "The full text extracted from the document via OCR",
  "summary": "A concise 1-2 sentence summary of the document's content and purpose",
  "action_items": ["List of key actions or follow-ups needed based on the document content"],
  "entities": {
    "Organization": "name if found",
    "Date": "date if found",
    "Currency": "currency if found",
    "Address": "address if found"
  },
  "amount": 0,
  "date": "date string",
  "merchant": "organization or sender name",
  "payment_method": "payment method if applicable, otherwise N/A",
  "items": ["list of line items if applicable"],
  "tax_amount": "tax amount if found or null",
  "tip_amount": "tip amount if found or null"
}

IMPORTANT RULES:
- "category" MUST be one of: {$quotedCategories}
- For receipts: extract amount, merchant, items, tax, tip, payment method
- For invoices: extract amount, organization, line items, due date
- For contracts, letters, and other documents: set amount to 0, focus on extracted_text, summary, action_items, and entities
- Do not include person names in the "entities" object
- Treat all provided images as one continuous document, merging information across pages in order
- De-duplicate repeated headers, footers, and totals that may appear on multiple pages
- Prefer the most complete and final totals when conflicts exist across pages
- "entities" should only include key-value pairs that are actually found in the document. Remove any entity keys with empty or unfound values
- "action_items" should be practical, actionable tasks derived from the document content
- Keep "extracted_text" in the source document language
- Keep the entity object shape stable and only use the keys shown above{$identityRule}{$languageInstructions}
- Do NOT include any additional text or markdown formatting outside the JSON object
PROMPT;
    }

    private function buildDocumentOcrParts(array $pages, array $allowedCategories, ?string $preferredLanguage = null): array
    {
        $parts = [
            ['text' => $this->buildDocumentOcrPrompt(count($pages), $allowedCategories, $preferredLanguage)],
        ];

        foreach ($pages as $page) {
            if (! $page instanceof UploadedFile) {
                continue;
            }

            $realPath = $page->getRealPath();

            if (! is_string($realPath) || $realPath === '') {
                continue;
            }

            $contents = file_get_contents($realPath);

            if ($contents === false) {
                continue;
            }

            $parts[] = [
                'inline_data' => [
                    'mime_type' => $this->resolveInlineMimeType($page),
                    'data' => base64_encode($contents),
                ],
            ];
        }

        if (count($parts) === 1) {
            throw GeminiApiException::malformedResponse(
                message: 'No readable document images were supplied for OCR analysis.',
            );
        }

        return $parts;
    }

    private function resolveInlineMimeType(UploadedFile $page): string
    {
        $detectedMimeType = $page->getMimeType();

        if (is_string($detectedMimeType) && in_array($detectedMimeType, self::SUPPORTED_INLINE_MIME_TYPES, true)) {
            return $detectedMimeType;
        }

        $clientMimeType = $page->getClientMimeType();

        if (is_string($clientMimeType) && in_array($clientMimeType, self::SUPPORTED_INLINE_MIME_TYPES, true)) {
            return $clientMimeType;
        }

        $extension = strtolower(
            $page->getClientOriginalExtension()
            ?: $page->guessExtension()
            ?: $page->extension()
            ?: pathinfo($page->getClientOriginalName(), PATHINFO_EXTENSION),
        );

        if ($extension !== '' && array_key_exists($extension, self::SUPPORTED_INLINE_MIME_TYPES)) {
            return self::SUPPORTED_INLINE_MIME_TYPES[$extension];
        }

        throw GeminiApiException::malformedResponse(
            message: 'Unsupported MIME type for OCR upload.',
            detail: [
                'client_mime_type' => $clientMimeType,
                'detected_mime_type' => $detectedMimeType,
                'filename' => $page->getClientOriginalName(),
            ],
        );
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

    private function normalizeAllowedCategories(array $allowedCategories): array
    {
        $normalized = [];

        foreach ($allowedCategories as $category) {
            if (! is_string($category)) {
                continue;
            }

            $clean = trim($category);

            if ($clean === '' || in_array($clean, $normalized, true)) {
                continue;
            }

            $normalized[] = $clean;
        }

        return $normalized;
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

    private function decodeJsonResponse(string $answer): array
    {
        $candidates = [];
        $trimmed = trim($answer);

        if ($trimmed !== '') {
            $candidates[] = $trimmed;
        }

        $withoutFences = trim((string) preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $trimmed));

        if ($withoutFences !== '' && ! in_array($withoutFences, $candidates, true)) {
            $candidates[] = $withoutFences;
        }

        $start = strpos($withoutFences, '{');
        $end = strrpos($withoutFences, '}');

        if ($start !== false && $end !== false && $end >= $start) {
            $jsonSlice = substr($withoutFences, $start, $end - $start + 1);

            if ($jsonSlice !== '' && ! in_array($jsonSlice, $candidates, true)) {
                $candidates[] = $jsonSlice;
            }
        }

        foreach ($candidates as $candidate) {
            try {
                $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);

                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (JsonException) {
                continue;
            }
        }

        throw GeminiApiException::malformedResponse(
            detail: ['response_text' => $answer],
        );
    }

    private function normalizeOcrPayload(array $payload, array $allowedCategories): array
    {
        $documentType = $this->normalizeDocumentType($payload['document_type'] ?? null);

        $category = $this->resolveAllowedCategory($payload['category'] ?? null, $allowedCategories);

        if ($category === null) {
            throw GeminiApiException::malformedResponse(
                message: 'Gemini API returned a category outside the allowed set.',
                detail: [
                    'category' => $payload['category'] ?? null,
                    'allowed_categories' => $allowedCategories,
                ],
            );
        }

        return [
            'document_type' => $documentType,
            'category' => $category,
            'extracted_text' => $this->normalizeString($payload['extracted_text'] ?? ''),
            'summary' => $this->normalizeString($payload['summary'] ?? ''),
            'action_items' => $this->normalizeActionItems($payload['action_items'] ?? [], $documentType),
            'entities' => $this->normalizeEntities($payload['entities'] ?? []),
            'amount' => $this->normalizeAmount($payload['amount'] ?? 0),
            'date' => $this->normalizeNullableString($payload['date'] ?? null),
            'merchant' => $this->normalizeNullableString($payload['merchant'] ?? null),
            'payment_method' => $this->normalizeNullableString($payload['payment_method'] ?? null) ?? 'N/A',
            'items' => $this->normalizeStringList($payload['items'] ?? []),
            'tax_amount' => $this->normalizeNullableScalar($payload['tax_amount'] ?? null),
            'tip_amount' => $this->normalizeNullableScalar($payload['tip_amount'] ?? null),
        ];
    }

    private function normalizeDocumentType(mixed $documentType): string
    {
        if (! is_string($documentType)) {
            return 'other';
        }

        $normalized = strtolower(trim($documentType));

        if ($normalized === '') {
            return 'other';
        }

        if (in_array($normalized, self::DOCUMENT_TYPES, true)) {
            return $normalized;
        }

        return match ($normalized) {
            'id', 'identity', 'identity_card', 'passport', 'driver_license' => 'personal',
            'bill', 'billing', 'statement' => 'invoice',
            default => 'other',
        };
    }

    private function resolveAllowedCategory(mixed $category, array $allowedCategories): ?string
    {
        if (! is_string($category)) {
            return null;
        }

        $clean = trim($category);

        foreach ($allowedCategories as $allowedCategory) {
            if ($clean === $allowedCategory || strcasecmp($clean, $allowedCategory) === 0) {
                return $allowedCategory;
            }
        }

        return null;
    }

    private function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }

            $clean = trim($item);

            if ($clean === '' || in_array($clean, $normalized, true)) {
                continue;
            }

            $normalized[] = $clean;
        }

        return $normalized;
    }

    private function normalizeActionItems(mixed $value, string $documentType): array
    {
        $normalized = $this->normalizeStringList($value);

        if ($normalized !== []) {
            return $normalized;
        }

        return match ($documentType) {
            'personal' => ['Review the extracted personal details and store this document securely'],
            'invoice' => ['Review the invoice details and schedule any required payment or follow-up'],
            'receipt' => ['Review the receipt details and file any related reimbursement or recordkeeping tasks'],
            default => [],
        };
    }

    private function normalizeEntities(mixed $entities): array
    {
        if (! is_array($entities)) {
            return [];
        }

        $normalized = [];

        foreach (self::ENTITY_ALIASES as $key => $aliases) {
            $value = $this->firstEntityValue($entities, $aliases);

            if (! is_string($value)) {
                continue;
            }

            $clean = trim($value);

            if ($clean === '') {
                continue;
            }

            $normalized[$key] = $clean;
        }

        return $normalized;
    }

    private function firstEntityValue(array $entities, array $aliases): mixed
    {
        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $entities)) {
                return $entities[$alias];
            }

            foreach ($entities as $key => $value) {
                if (is_string($key) && strcasecmp($key, $alias) === 0) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function normalizeString(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $clean = trim($value);

        return $clean === '' ? null : $clean;
    }

    private function normalizeNullableScalar(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $clean = trim($value);

            return $clean === '' || strtolower($clean) === 'null' ? null : $clean;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return null;
    }

    private function normalizeAmount(mixed $value): int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            $clean = preg_replace('/[^0-9.\-]/', '', $value) ?? '';

            if ($clean !== '' && is_numeric($clean)) {
                return str_contains($clean, '.') ? (float) $clean : (int) $clean;
            }
        }

        return 0;
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
