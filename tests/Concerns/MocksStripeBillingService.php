<?php

namespace Tests\Concerns;

use App\Services\StripeBillingService;
use PHPUnit\Framework\MockObject\MockObject;

trait MocksStripeBillingService
{
    private function mockStripeBillingService(): MockObject&StripeBillingService
    {
        $mock = $this->createMock(StripeBillingService::class);
        $this->app->instance(StripeBillingService::class, $mock);

        return $mock;
    }
}