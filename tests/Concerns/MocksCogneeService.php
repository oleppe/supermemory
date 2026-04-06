<?php

namespace Tests\Concerns;

use App\Services\CogneeService;
use PHPUnit\Framework\MockObject\MockObject;

trait MocksCogneeService
{
    private function mockCogneeService(): MockObject&CogneeService
    {
        $mock = $this->createMock(CogneeService::class);
        $this->app->instance(CogneeService::class, $mock);

        return $mock;
    }
}
