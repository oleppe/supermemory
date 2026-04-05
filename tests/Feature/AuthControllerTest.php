<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MocksSupermemoryService;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use MocksSupermemoryService;
    use RefreshDatabase;

    public function test_register_creates_user_and_returns_token(): void
    {
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
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
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
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Invalid credentials');
    }

    public function test_me_returns_user_with_supermemory_context(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $mock = $this->mockSupermemoryService();
        $mock->expects($this->once())
            ->method('isConfigured')
            ->willReturn(true);

        $response = $this->actingAs($user)
            ->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'email', 'name'],
                'supermemory' => ['configured', 'container_tag'],
            ]);
        $response->assertJsonPath('supermemory.container_tag', 'user-'.$user->id);
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();

        $token = $user->createToken('flutter')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/auth/logout');

        $response->assertOk()
            ->assertJson(['message' => 'Logged out']);

        // Token should be revoked
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }
}
