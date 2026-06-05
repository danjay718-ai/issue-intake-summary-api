<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Registration
    // -----------------------------------------------------------------------

    public function test_register_with_valid_data_returns_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Dana',
            'email'                 => 'dana@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->assertDatabaseHas('users', ['email' => 'dana@example.com']);
    }

    public function test_register_with_duplicate_email_returns_422(): void
    {
        User::factory()->create(['email' => 'dana@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name'                  => 'Dana',
            'email'                 => 'dana@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['email']]);
    }

    // -----------------------------------------------------------------------
    // Login
    // -----------------------------------------------------------------------

    public function test_login_with_valid_credentials_returns_token(): void
    {
        User::factory()->create([
            'email'    => 'dana@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'dana@example.com',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);
    }

    public function test_login_with_wrong_password_returns_401(): void
    {
        User::factory()->create([
            'email'    => 'dana@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'dana@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    // -----------------------------------------------------------------------
    // Protected route access
    // -----------------------------------------------------------------------

    public function test_protected_route_without_token_returns_401(): void
    {
        $response = $this->getJson('/api/v1/issues');

        $response->assertUnauthorized();
    }

    public function test_protected_route_with_valid_token_returns_200(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/v1/issues');

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    // -----------------------------------------------------------------------
    // Logout
    // -----------------------------------------------------------------------

    public function test_logout_revokes_token_and_subsequent_request_returns_401(): void
    {
        $user  = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        // Logout using the token
        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        // Reset the resolved auth guard so the next request re-checks the token from DB
        $this->app->make('auth')->forgetGuards();

        // Same token should now be rejected — it was deleted on logout
        $this->withToken($token)
            ->getJson('/api/v1/issues')
            ->assertUnauthorized();
    }
}
