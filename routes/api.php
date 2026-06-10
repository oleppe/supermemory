<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\IngestionController;
use App\Http\Controllers\OcrController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\AdminPlanController;
use App\Http\Controllers\AdminSubscriptionController;
use App\Http\Controllers\Api\TranslationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes for the Flutter ↔ Supermemory API layer.
| All routes are prefixed with /api automatically by Laravel.
|
*/

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->middleware('throttle:api');

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:auth');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::patch('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::get('/system/health', [SystemController::class, 'health']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/memories', [IngestionController::class, 'storeMemory'])
        ->middleware('throttle:ingestion');

    Route::post('/documents', [IngestionController::class, 'storeDocuments'])
        ->middleware('throttle:ingestion');

    Route::post('/translate', [TranslationController::class, 'translate'])
        ->middleware(['throttle:api', 'paid_subscription']);

    Route::post('/ocr/analyze', [OcrController::class, 'analyze'])
        ->middleware('throttle:processing');


    Route::get('/documents', [DocumentController::class, 'index'])
        ->middleware('throttle:api');

    Route::get('/documents/{documentId}', [DocumentController::class, 'show'])
        ->middleware('throttle:api');

    Route::get('/plans', [PlanController::class, 'index'])
        ->middleware('throttle:api');

    Route::get('/subscription', [SubscriptionController::class, 'show'])
        ->middleware('throttle:api');

    Route::get('/billing/overview', [SubscriptionController::class, 'overview'])
        ->middleware('throttle:api');

    Route::post('/subscription/payment-sheet', [SubscriptionController::class, 'createPaymentSheet'])
        ->middleware('throttle:api');

    Route::post('/subscription/checkout-session', [SubscriptionController::class, 'createPaymentSheet'])
        ->middleware('throttle:api');

    Route::post('/subscription/portal-session', [SubscriptionController::class, 'createBillingPortalSession'])
        ->middleware('throttle:api');

    Route::post('/subscription/refresh', [SubscriptionController::class, 'refresh'])
        ->middleware('throttle:api');

    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel'])
        ->middleware('throttle:api');

    Route::post('/subscription/resume', [SubscriptionController::class, 'resume'])
        ->middleware('throttle:api');

    Route::get('/usage', [SubscriptionController::class, 'usage'])
        ->middleware('throttle:api');

    Route::get('/usage/counters', [SubscriptionController::class, 'counters'])
        ->middleware('throttle:api');

    Route::post('/search/memories', [SearchController::class, 'searchMemories'])
        ->middleware('throttle:search');

    Route::post('/search/documents', [SearchController::class, 'searchDocuments'])
        ->middleware('throttle:search');

    Route::get('/system/connection', [SystemController::class, 'connection'])
        ->middleware('throttle:api');

    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/plans', [AdminPlanController::class, 'index'])
            ->middleware('throttle:api');

        Route::post('/plans', [AdminPlanController::class, 'store'])
            ->middleware('throttle:api');

        Route::patch('/plans/{plan}', [AdminPlanController::class, 'update'])
            ->middleware('throttle:api');

        Route::get('/subscriptions', [AdminSubscriptionController::class, 'index'])
            ->middleware('throttle:api');

        Route::get('/users/{user}/subscription', [AdminSubscriptionController::class, 'show'])
            ->middleware('throttle:api');

        Route::put('/users/{user}/subscription', [AdminSubscriptionController::class, 'assign'])
            ->middleware('throttle:api');
    });
});
