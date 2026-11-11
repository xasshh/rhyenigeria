<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_log_in_and_receives_a_token(): void
    {
        $user = User::factory()->agent()->create(['email' => 'agent@example.com']);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'Agent@Example.com',
            'password' => 'password',
            'device_name' => 'Pixel 8',
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.role', 'field_agent')
            ->assertJsonMissingPath('user.password');

        $this->assertNotEmpty($response->json('token'));
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id, 'name' => 'Pixel 8']);
    }

    public function test_login_fails_with_a_wrong_password(): void
    {
        User::factory()->create(['email' => 'agent@example.com']);

        $this->postJson('/api/auth/login', ['email' => 'agent@example.com', 'password' => 'nope', 'device_name' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_a_deactivated_user_cannot_log_in(): void
    {
        User::factory()->inactive()->create(['email' => 'gone@example.com']);

        $this->postJson('/api/auth/login', ['email' => 'gone@example.com', 'password' => 'password', 'device_name' => 'x'])
            ->assertForbidden();
    }

    public function test_a_deactivated_user_with_an_old_token_is_refused(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phone')->plainTextToken;
        $user->forceFill(['is_active' => false])->save();

        $this->withToken($token)->getJson('/api/auth/me')->assertForbidden();
    }

    public function test_protected_endpoints_require_a_token(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        $this->getJson('/api/map/positions')->assertUnauthorized();
        $this->postJson('/api/locations', [])->assertUnauthorized();
        $this->postJson('/api/broadcasting/auth', [])->assertUnauthorized();
    }

    public function test_the_token_identifies_the_user(): void
    {
        $user = User::factory()->coordinator()->create();
        $token = $user->createToken('laptop')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.role', 'coordinator');
    }

    public function test_logging_out_revokes_the_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'bad', 'device_name' => 'x'])->assertStatus(422);
        }

        $this->postJson('/api/auth/login', ['email' => 'a@example.com', 'password' => 'bad', 'device_name' => 'x'])->assertStatus(429);
    }
}
