<?php

namespace Tests\Feature;

use App\Exceptions\CogneeApiException;
use App\Models\User;
use App\Services\CogneeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    private function mockCogneeService(): CogneeService
    {
        $mock = $this->createMock(CogneeService::class);
        $this->app->instance(CogneeService::class, $mock);

        return $mock;
    }

    public function test_register_creates_user_and_returns_token(): void
    {
        $mock = $this->mockCogneeService();

        $mock->expects($this->once())
            ->method('register')
            ->with('test@example.com', 'password123')
            ->willReturn([
                'id' => 'cognee-uuid',
                'email' => 'test@example.com',
                'is_active' => true,
                'is_superuser' => false,
                'is_verified' => true,
            ]);

        $mock->expects($this->once())
            ->method('login')
            ->with('test@example.com', 'password123')
            ->willReturn([
                'token' => 'cognee-auth-token-abc',
                'response' => [],
            ]);

        $response = $this->postJson('/api/auth/register', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'user' => ['id', 'email', 'name'],
                'token',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
        ]);

        $this->assertNotNull(User::where('email', 'test@example.com')->first()?->cognee_password);
    }

    public function test_register_validates_email_required(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_validates_password_min_length(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => 'test@example.com',
            'password' => 'ab',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'test@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_returns_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $mock = $this->mockCogneeService();

        $mock->expects($this->once())
            ->method('login')
            ->with('test@example.com', 'password123')
            ->willReturn([
                'token' => 'cognee-auth-token-xyz',
                'response' => [],
            ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'email', 'name'],
                'token',
            ]);

        $user->refresh();
        $this->assertNotNull($user->cognee_password);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $mock = $this->mockCogneeService();

        // Cognee login succeeds (password is valid on Cognee side)
        $mock->method('login')
            ->willReturn([
                'token' => 'cognee-token',
                'response' => [],
            ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_fails_when_cognee_rejects(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $mock = $this->mockCogneeService();

        $mock->method('login')
            ->willThrowException(new CogneeApiException(
                statusCode: 400,
                cogneeDetail: 'LOGIN_BAD_CREDENTIALS',
                message: 'LOGIN_BAD_CREDENTIALS',
            ));

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(400)
            ->assertJson(['detail' => 'LOGIN_BAD_CREDENTIALS']);
    }

    public function test_me_returns_user_with_cognee_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'cognee_token' => 'cognee-token-123',
        ]);

        $mock = $this->mockCogneeService();

        $mock->expects($this->once())
            ->method('me')
            ->willReturn([
                'id' => 'cognee-uuid',
                'email' => 'test@example.com',
                'is_active' => true,
                'is_superuser' => false,
                'is_verified' => true,
            ]);

        $response = $this->actingAs($user)
            ->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'email', 'name'],
                'cognee',
            ]);
    }

    public function test_me_restores_expired_cognee_session_when_password_is_available(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'cognee_token' => 'expired-token',
            'cognee_password' => 'password123',
        ]);

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

        $response = $this->actingAs($user)
            ->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('cognee.id', 'cognee-user');
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create([
            'cognee_token' => 'cognee-token-123',
        ]);

        $mock = $this->mockCogneeService();
        $mock->expects($this->once())->method('logout');

        $token = $user->createToken('flutter')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/auth/logout');

        $response->assertOk()
            ->assertJson(['message' => 'Logged out']);

        // Token should be revoked
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Cognee token should be cleared
        $user->refresh();
        $this->assertNull($user->cognee_token);
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }
}
