<?php

namespace Tests\Feature;

use App\Exceptions\GeminiApiException;
use App\Models\Plan;
use App\Models\User;
use App\Models\UsageCounter;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\MocksGeminiService;
use Tests\TestCase;

class OcrControllerTest extends TestCase
{
    use MocksGeminiService;
    use RefreshDatabase;

    public function test_analyze_requires_authentication(): void
    {
        $this->postJson('/api/ocr/analyze', [
            'allowed_categories' => ['expenses'],
        ])->assertStatus(401);
    }

    public function test_analyze_validates_required_inputs(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")
            ->withHeader('Accept', 'application/json')
            ->post('/api/ocr/analyze', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pages', 'allowed_categories']);
    }

    public function test_analyze_validates_image_types(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;
        $file = UploadedFile::fake()->createWithContent('page-1.txt', 'not-an-image');

        $this->withHeader('Authorization', "Bearer $token")
            ->withHeader('Accept', 'application/json')
            ->post('/api/ocr/analyze', [
                'pages' => [$file],
                'allowed_categories' => ['expenses'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['pages.0']);
    }

    public function test_analyze_accepts_pdf_pages(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;
        $pdfPage = UploadedFile::fake()->create('page-1.pdf', 100, 'application/pdf');
        $gemini = $this->mockGeminiService();

        $gemini->expects($this->once())
            ->method('analyzeDocumentImages')
            ->with(
                $this->callback(fn (array $pages): bool => count($pages) === 1 && $pages[0] instanceof UploadedFile),
                ['expenses'],
                null,
            )
            ->willReturn([
                'document_type' => 'receipt',
                'category' => 'expenses',
                'extracted_text' => 'Receipt text',
                'summary' => 'Receipt summary',
                'action_items' => ['Submit expense report'],
                'entities' => ['Organization' => 'Acme Cafe'],
                'amount' => 42.5,
                'date' => '2026-04-07',
                'merchant' => 'Acme Cafe',
                'payment_method' => 'Visa',
                'items' => ['Lunch combo'],
                'tax_amount' => '3.50',
                'tip_amount' => null,
            ]);

        $gemini->expects($this->once())
            ->method('model')
            ->willReturn('gemini-2.5-flash');

        $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/ocr/analyze', [
                'pages' => [$pdfPage],
                'allowed_categories' => ['expenses'],
            ])
            ->assertOk()
            ->assertJsonPath('data.category', 'expenses')
            ->assertJsonPath('meta.page_count', 1);
    }

    public function test_analyze_returns_structured_ocr_data(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;
        $firstPage = UploadedFile::fake()->image('page-1.jpg');
        $secondPage = UploadedFile::fake()->image('page-2.png');
        $gemini = $this->mockGeminiService();

        $gemini->expects($this->once())
            ->method('analyzeDocumentImages')
            ->with(
                $this->callback(fn (array $pages): bool => count($pages) === 2
                    && $pages[0] instanceof UploadedFile
                    && $pages[1] instanceof UploadedFile),
                ['expenses', 'travel'],
                null,
            )
            ->willReturn([
                'document_type' => 'receipt',
                'category' => 'expenses',
                'extracted_text' => 'Receipt text',
                'summary' => 'Receipt summary',
                'action_items' => ['Submit expense report'],
                'entities' => ['Organization' => 'Acme Cafe'],
                'amount' => 42.5,
                'date' => '2026-04-07',
                'merchant' => 'Acme Cafe',
                'payment_method' => 'Visa',
                'items' => ['Lunch combo'],
                'tax_amount' => '3.50',
                'tip_amount' => null,
            ]);

        $gemini->expects($this->once())
            ->method('model')
            ->willReturn('gemini-2.5-flash');

        $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/ocr/analyze', [
                'pages' => [$firstPage, $secondPage],
                'allowed_categories' => ['expenses', 'travel'],
            ])
            ->assertOk()
            ->assertJsonPath('data.document_type', 'receipt')
            ->assertJsonPath('data.category', 'expenses')
            ->assertJsonPath('data.amount', 42.5)
            ->assertJsonPath('meta.page_count', 2)
            ->assertJsonPath('meta.allowed_categories.0', 'expenses')
            ->assertJsonPath('meta.model', 'gemini-2.5-flash');
    }

    public function test_analyze_passes_saved_user_language_to_gemini_service(): void
    {
        $user = User::factory()->create([
            'preferred_language' => 'fr',
        ]);
        $token = $user->createToken('flutter')->plainTextToken;
        $file = UploadedFile::fake()->image('page-1.jpg');
        $gemini = $this->mockGeminiService();

        $gemini->expects($this->once())
            ->method('analyzeDocumentImages')
            ->with(
                $this->callback(fn (array $pages): bool => count($pages) === 1 && $pages[0] instanceof UploadedFile),
                ['expenses'],
                'fr',
            )
            ->willReturn([
                'document_type' => 'receipt',
                'category' => 'expenses',
                'extracted_text' => 'Texte du recu',
                'summary' => 'Resume du recu',
                'action_items' => ['Soumettre la note de frais'],
                'entities' => ['Organization' => 'Acme Cafe'],
                'amount' => 42.5,
                'date' => '2026-04-07',
                'merchant' => 'Acme Cafe',
                'payment_method' => 'Visa',
                'items' => ['Lunch combo'],
                'tax_amount' => '3.50',
                'tip_amount' => null,
            ]);

        $gemini->expects($this->once())
            ->method('model')
            ->willReturn('gemini-2.5-flash');

        $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/ocr/analyze', [
                'pages' => [$file],
                'allowed_categories' => ['expenses'],
            ])
            ->assertOk()
            ->assertJsonPath('data.summary', 'Resume du recu');
    }

    public function test_analyze_surfaces_gemini_errors(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;
        $file = UploadedFile::fake()->image('page-1.jpg');
        $gemini = $this->mockGeminiService();

        $gemini->expects($this->once())
            ->method('analyzeDocumentImages')
            ->willThrowException(GeminiApiException::malformedResponse(
                message: 'Gemini API returned malformed JSON',
                detail: ['response_text' => 'nope'],
            ));

        $gemini->expects($this->never())
            ->method('model');

        $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/ocr/analyze', [
                'pages' => [$file],
                'allowed_categories' => ['expenses'],
            ])
            ->assertStatus(502)
            ->assertJsonPath('message', 'Gemini API returned malformed JSON')
            ->assertJsonPath('detail.response_text', 'nope');
    }

    public function test_analyze_rejects_when_pro_daily_ai_limit_is_exhausted(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;
        $subscriptionService = app(SubscriptionService::class);
        $proPlan = Plan::query()->where('code', 'pro')->firstOrFail();
        $subscription = $subscriptionService->assignPlan($user, $proPlan, $admin, 'Upgraded for test.');

        UsageCounter::query()->create([
            'user_id' => $user->id,
            'metric' => 'ai_questions',
            'period' => 'daily',
            'period_start' => now()->startOfDay(),
            'period_end' => now()->startOfDay()->addDay(),
            'used' => 50,
        ]);

        $gemini = $this->mockGeminiService();
        $gemini->expects($this->never())
            ->method('analyzeDocumentImages');

        $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/ocr/analyze', [
                'pages' => [UploadedFile::fake()->image('page-1.jpg')],
                'allowed_categories' => ['expenses'],
            ])
            ->assertStatus(429)
            ->assertJsonPath('detail.metric', 'ai_questions')
            ->assertJsonPath('detail.period', 'daily')
            ->assertJsonPath('detail.limit', 50);

        $this->assertSame($subscription->id, $subscriptionService->resolveActiveSubscription($user)->id);
    }
}
