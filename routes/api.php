<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\IngestionController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SystemController;
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
    Route::post('/memories', [IngestionController::class, 'storeMemory'])
        ->middleware('throttle:ingestion');

    Route::post('/documents', [IngestionController::class, 'storeDocuments'])
        ->middleware('throttle:ingestion');


    Route::get('/documents', [DocumentController::class, 'index'])
        ->middleware('throttle:api');

    Route::get('/documents/{documentId}', [DocumentController::class, 'show'])
        ->middleware('throttle:api');

    Route::post('/search/memories', [SearchController::class, 'searchMemories'])
        ->middleware('throttle:search');

    Route::post('/search/documents', [SearchController::class, 'searchDocuments'])
        ->middleware('throttle:search');

    Route::get('/system/connection', [SystemController::class, 'connection'])
        ->middleware('throttle:api');
});
