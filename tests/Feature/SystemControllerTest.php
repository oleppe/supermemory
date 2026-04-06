<?php

namespace Tests\Feature;

use App\Exceptions\CogneeApiException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MocksCogneeService;
use Tests\TestCase;

class SystemControllerTest extends TestCase
{
    use MocksCogneeService;
    use RefreshDatabase;

    public function test_health_returns_cognee_status(): void
    {
        $mock = $this->mockCogneeService();

        $mock->expects($this->once())
            ->method('health')
            ->willReturn(['status' => 'ok']);

        $this->getJson('/api/system/health')
            ->assertOk()
            ->assertJsonPath('cognee.reachable', true)
            ->assertJsonPath('cognee.health.status', 'ok');
    }

    public function test_health_returns_service_unavailable_when_cognee_is_down(): void
    {
        $mock = $this->mockCogneeService();

        $mock->expects($this->once())
            ->method('health')
            ->willThrowException(new CogneeApiException(503, 'down', 'down'));

        $this->getJson('/api/system/health')
            ->assertStatus(503)
            ->assertJsonPath('cognee.reachable', false);
    }

    public function test_connection_requires_authenticated_cognee_session(): void
    {
        $user = User::factory()->create(['cognee_token' => 'cognee-token']);
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockCogneeService();

        $mock->expects($this->once())
            ->method('checkConnection')
            ->with('cognee-token')
            ->willReturn(['connected' => true]);

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/system/connection')
            ->assertOk()
            ->assertJsonPath('data.connected', true);
    }

    public function test_connection_restores_expired_cognee_session_and_retries(): void
    {
        $user = User::factory()->create([
            'cognee_token' => 'expired-token',
            'cognee_password' => 'password123',
        ]);
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockCogneeService();

        $mock->expects($this->exactly(2))
            ->method('checkConnection')
            ->with($this->logicalOr('expired-token', 'restored-token'))
            ->willReturnCallback(function (string $token) {
                if ($token === 'expired-token') {
                    throw CogneeApiException::sessionExpired();
                }

                return ['connected' => true];
            });

        $mock->expects($this->once())
            ->method('restoreUserSession')
            ->with($this->callback(fn (User $model) => $model->is($user)))
            ->willReturn('restored-token');

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/system/connection')
            ->assertOk()
            ->assertJsonPath('data.connected', true);
    }
}
