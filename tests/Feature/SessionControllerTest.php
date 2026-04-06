<?php

namespace Tests\Feature;

use App\Exceptions\CogneeApiException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MocksCogneeService;
use Tests\TestCase;

class SessionControllerTest extends TestCase
{
    use MocksCogneeService;
    use RefreshDatabase;

    public function test_status_returns_active_cognee_session(): void
    {
        $user = User::factory()->create(['cognee_token' => 'cognee-token']);
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockCogneeService();

        $mock->expects($this->once())
            ->method('me')
            ->with('cognee-token')
            ->willReturn(['id' => 'cognee-user']);

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/session/status')
            ->assertOk()
            ->assertJsonPath('cognee.status', 'active')
            ->assertJsonPath('cognee.authenticated', true);
    }

    public function test_status_returns_expired_when_cognee_session_is_invalid(): void
    {
        $user = User::factory()->create(['cognee_token' => 'cognee-token']);
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockCogneeService();

        $mock->expects($this->once())
            ->method('me')
            ->with('cognee-token')
            ->willThrowException(CogneeApiException::sessionExpired());

        $mock->expects($this->once())
            ->method('restoreUserSession')
            ->with($this->callback(fn (User $model) => $model->is($user)))
            ->willThrowException(CogneeApiException::sessionRecoveryUnavailable());

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/session/status')
            ->assertOk()
            ->assertJsonPath('cognee.status', 'requires_credentials')
            ->assertJsonPath('cognee.authenticated', false)
            ->assertJsonPath('cognee.can_restore_automatically', false)
            ->assertJsonPath('cognee.detail.code', 'COGNEE_SESSION_RECOVERY_UNAVAILABLE');
    }

    public function test_status_restores_expired_session_when_reauthentication_is_possible(): void
    {
        $user = User::factory()->create([
            'cognee_token' => 'expired-token',
            'cognee_password' => 'password123',
        ]);
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockCogneeService();

        $mock->expects($this->exactly(2))
            ->method('me')
            ->with($this->logicalOr('expired-token', 'restored-token'))
            ->willReturnCallback(function (string $token) {
                if ($token === 'expired-token') {
                    throw CogneeApiException::sessionExpired();
                }

                return ['id' => 'cognee-user'];
            });

        $mock->expects($this->once())
            ->method('restoreUserSession')
            ->with($this->callback(fn (User $model) => $model->is($user)))
            ->willReturn('restored-token');

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/session/status')
            ->assertOk()
            ->assertJsonPath('cognee.status', 'active')
            ->assertJsonPath('cognee.authenticated', true)
            ->assertJsonPath('cognee.can_restore_automatically', true)
            ->assertJsonPath('cognee.profile.id', 'cognee-user');
    }

    public function test_status_returns_missing_when_no_cognee_token_is_present(): void
    {
        $user = User::factory()->create(['cognee_token' => null]);
        $token = $user->createToken('flutter')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/session/status')
            ->assertOk()
            ->assertJsonPath('cognee.status', 'missing')
            ->assertJsonPath('cognee.can_restore_automatically', false);
    }

    public function test_restore_uses_stored_password_when_available(): void
    {
        $user = User::factory()->create([
            'cognee_token' => 'expired-token',
            'cognee_password' => 'password123',
        ]);
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockCogneeService();

        $mock->expects($this->once())
            ->method('restoreUserSession')
            ->with($this->callback(fn (User $model) => $model->is($user)))
            ->willReturn('restored-token');

        $mock->expects($this->once())
            ->method('me')
            ->with('restored-token')
            ->willReturn(['id' => 'cognee-user']);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/session/restore')
            ->assertOk()
            ->assertJsonPath('message', 'Cognee session restored')
            ->assertJsonPath('cognee.status', 'active')
            ->assertJsonPath('cognee.profile.id', 'cognee-user');
    }

    public function test_restore_accepts_password_when_automatic_recovery_is_unavailable(): void
    {
        $user = User::factory()->create([
            'cognee_token' => null,
            'cognee_password' => null,
        ]);
        $token = $user->createToken('flutter')->plainTextToken;
        $mock = $this->mockCogneeService();

        $mock->expects($this->once())
            ->method('authenticateUser')
            ->with($this->callback(fn (User $model) => $model->is($user)), 'password123', true)
            ->willReturn('restored-token');

        $mock->expects($this->once())
            ->method('me')
            ->with('restored-token')
            ->willReturn(['id' => 'cognee-user']);

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/session/restore', [
                'password' => 'password123',
            ])
            ->assertOk()
            ->assertJsonPath('cognee.status', 'active')
            ->assertJsonPath('cognee.can_restore_automatically', true)
            ->assertJsonPath('cognee.profile.id', 'cognee-user');
    }

    public function test_restore_requires_password_when_no_stored_cognee_password_exists(): void
    {
        $user = User::factory()->create([
            'cognee_token' => null,
            'cognee_password' => null,
        ]);
        $token = $user->createToken('flutter')->plainTextToken;

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/session/restore')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }
}
