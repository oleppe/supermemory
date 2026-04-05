<?php

namespace Tests\Concerns;

use App\Services\GeminiService;
use PHPUnit\Framework\MockObject\MockObject;

trait MocksGeminiService
{
    private function mockGeminiService(): MockObject&GeminiService
    {
        $mock = $this->createMock(GeminiService::class);
        $this->app->instance(GeminiService::class, $mock);

        return $mock;
    }
}
