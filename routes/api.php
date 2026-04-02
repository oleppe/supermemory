<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CognifyController;
use App\Http\Controllers\DatasetController;
use App\Http\Controllers\IngestionController;
use App\Http\Controllers\MemifyController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SystemController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Routes for the Flutter ↔ Cognee API layer.
| All routes are prefixed with /api automatically by Laravel.
|
*/

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:auth');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::get('/system/health', [SystemController::class, 'health']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/session/status', [SessionController::class, 'status'])
        ->middleware('throttle:api');

    Route::post('/session/restore', [SessionController::class, 'restore'])
        ->middleware('throttle:auth');

    Route::get('/datasets', [DatasetController::class, 'index'])
        ->middleware('throttle:api');

    Route::post('/datasets', [DatasetController::class, 'store'])
        ->middleware('throttle:processing');

    Route::get('/datasets/status', [DatasetController::class, 'status'])
        ->middleware('throttle:processing');

    Route::post('/ingestion/text', [IngestionController::class, 'storeText'])
        ->middleware('throttle:ingestion');

    Route::post('/ingestion/files', [IngestionController::class, 'storeFiles'])
        ->middleware('throttle:ingestion');

    Route::post('/cognify', [CognifyController::class, 'store'])
        ->middleware('throttle:processing');

    Route::post('/memify', [MemifyController::class, 'store'])
        ->middleware('throttle:processing');

    Route::post('/search', [SearchController::class, 'store'])
        ->middleware('throttle:search');

    Route::get('/search/history', [SearchController::class, 'history'])
        ->middleware('throttle:search');

    Route::get('/system/connection', [SystemController::class, 'connection'])
        ->middleware('throttle:api');
});
