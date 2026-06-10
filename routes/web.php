<?php

use App\Http\Controllers\ContactUsController;
use App\Http\Controllers\LandingDemoRequestController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->middleware('throttle:api');

Route::get('/', function () {
    return view('home');
});

Route::get('/privacy-policy', function () {
    return view('privacy-policy');
});

Route::get('/contact-us', [ContactUsController::class, 'show']);
Route::post('/contact-us', [ContactUsController::class, 'store']);

Route::post('/demo-request', LandingDemoRequestController::class);

Route::get('/admin/{any?}', function () {
    return view('application');
})->where('any', '.*');
