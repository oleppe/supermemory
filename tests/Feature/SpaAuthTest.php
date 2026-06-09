<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MocksSupermemoryService;
use Tests\TestCase;

class SpaAuthTest extends TestCase
{
    use MocksSupermemoryService;
    use RefreshDatabase;

    public function test_spa_login_sets_session_cookie(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response = $this->withHeader('Origin', 'http://localhost')
            ->withHeader('Referer', 'http://localhost/')
            ->postJson('/api/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password123',
            ]);

        $response->assertOk()
            ->assertCookieNotExpired('laravel-session');
    }

    public function test_spa_session_authenticates_me_endpoint(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
        ]);

        // Login to establish session
        $this->withHeader('Origin', 'http://localhost')
            ->withHeader('Referer', 'http://localhost/')
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        // Use session cookie to access /me
        $response = $this->withHeader('Origin', 'http://localhost')
            ->withHeader('Referer', 'http://localhost/')
            ->getJson('/api/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_spa_logout_invalidates_session(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
        ]);

        // Login to establish session
        $this->withHeader('Origin', 'http://localhost')
            ->withHeader('Referer', 'http://localhost/')
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        // Logout
        $response = $this->withHeader('Origin', 'http://localhost')
            ->withHeader('Referer', 'http://localhost/')
            ->postJson('/api/auth/logout');

        $response->assertOk()
            ->assertJson(['message' => 'Logged out']);

        // Reset cached auth guards so the next request resolves fresh
        $this->app['auth']->forgetGuards();

        // Session should be invalidated — /me should return 401
        $response = $this->withHeader('Origin', 'http://localhost')
            ->withHeader('Referer', 'http://localhost/')
            ->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    public function test_spa_register_creates_session(): void
    {
        $response = $this->withHeader('Origin', 'http://localhost')
            ->withHeader('Referer', 'http://localhost/')
            ->postJson('/api/auth/register', [
                'email' => 'new@example.com',
                'password' => 'password123',
            ]);

        $response->assertStatus(201)
            ->assertCookieNotExpired('laravel-session');

        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
        ]);
    }
}
