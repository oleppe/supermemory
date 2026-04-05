<?php

namespace Tests\Feature;

use App\Exceptions\SupermemoryApiException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MocksSupermemoryService;
use Tests\TestCase;

class SystemControllerTest extends TestCase
{
    use MocksSupermemoryService;
    use RefreshDatabase;

    public function test_health_returns_supermemory_status(): void
    {
        $mock = $this->mockSupermemoryService();

        $mock->expects($this->once())
            ->method('health')
            ->willReturn(['documents' => []]);

        $this->getJson('/api/system/health')
            ->assertOk()
            ->assertJsonPath('supermemory.reachable', true);
    }

    public function test_health_returns_service_unavailable_when_supermemory_is_down(): void
    {
        $mock = $this->mockSupermemoryService();

        $mock->expects($this->once())
            ->method('health')
            ->willThrowException(new SupermemoryApiException(503, 'down', 'down'));

        $this->getJson('/api/system/health')
            ->assertStatus(503)
            ->assertJsonPath('supermemory.reachable', false);
    }

    public function test_connection_uses_authenticated_users_container_tag(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockSupermemoryService();

        $mock->expects($this->once())
            ->method('checkConnection')
            ->with('user-'.$user->id)
            ->willReturn(['connected' => true, 'container_tag' => 'user-'.$user->id]);

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/system/connection')
            ->assertOk()
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.container_tag', 'user-'.$user->id);
    }
}
