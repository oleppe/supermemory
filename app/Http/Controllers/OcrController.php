<?php

namespace App\Http\Controllers;

use App\Http\Requests\AnalyzeDocumentOcrRequest;
use App\Services\GeminiService;
use App\Services\UsageLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class OcrController extends Controller
{
    public function __construct(
        private readonly GeminiService $geminiService,
        private readonly UsageLimitService $usageLimitService,
    ) {}

    public function analyze(AnalyzeDocumentOcrRequest $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);
        $this->usageLimitService->ensureAiQuestionAllowed($user);

        $pages = array_values(array_filter(
            $request->file('pages', []),
            fn (mixed $page): bool => $page instanceof UploadedFile,
        ));

        $data = $this->geminiService->analyzeDocumentImages(
            $pages,
            $request->validated('allowed_categories'),
            $user->preferred_language,
        );

        $this->usageLimitService->consumeAiQuestions($user);

        return response()->json([
            'data' => $data,
            'meta' => [
                'page_count' => count($pages),
                'allowed_categories' => $request->validated('allowed_categories'),
                'model' => $this->geminiService->model(),
            ],
        ]);
    }
}
