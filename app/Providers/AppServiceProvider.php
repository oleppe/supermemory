<?php

namespace App\Providers;

use App\Services\FirestoreSyncService;
use App\Services\GeminiService;
use App\Services\PdfPageCounter;
use App\Services\StripeBillingService;
use App\Services\SubscriptionService;
use App\Services\SupermemoryService;
use App\Services\UsageLimitService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(FirestoreSyncService::class);
        $this->app->singleton(GeminiService::class);
        $this->app->singleton(PdfPageCounter::class);
        $this->app->singleton(StripeBillingService::class);
        $this->app->singleton(SubscriptionService::class);
        $this->app->singleton(SupermemoryService::class);
        $this->app->singleton(UsageLimitService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('ingestion', function (Request $request) {
            return Limit::perMinute(15)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('processing', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
