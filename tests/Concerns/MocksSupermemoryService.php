<?php

namespace Tests\Concerns;

use App\Services\SupermemoryService;
use PHPUnit\Framework\MockObject\MockObject;

trait MocksSupermemoryService
{
    private function mockSupermemoryService(): MockObject&SupermemoryService
    {
        $mock = $this->createMock(SupermemoryService::class);
        $this->app->instance(SupermemoryService::class, $mock);

        return $mock;
    }
}
