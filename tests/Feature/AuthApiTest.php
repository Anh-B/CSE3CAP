<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers AuthController (routes: POST /api/register, /api/login, /api/logout)
 * There wasn't a dedicated test file for these from Sprint 5 - this closes
 * that gap, and covers the new 'role' field from Sprint 6.
 */
class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    // ---------- register() ----------

    public function test_can_register_and_defaults_to_the_student_role(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.user.role', 'student')
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $this->assertDatabaseHas('users', ['email' => 'alice@example.com', 'role' => 'student']);
    }

    public function test_can_register_as_an_assessor(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Assessor Bob',
            'email' => 'bob@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'assessor',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.user.role', 'assessor');
        $this->assertDatabaseHas('users', ['email' => 'bob@example.com', 'role' => 'assessor']);
    }

    public function test_rejects_an_invalid_role(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Carl',
            'email' => 'carl@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin', // not a real role
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['role']);
    }

    public function test_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Dana',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_rejects_a_short_password(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Erin',
            'email' => 'erin@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    public function test_rejects_a_mismatched_password_confirmation(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Frank',
            'email' => 'frank@example.com',
            'password' => 'password123',
            'password_confirmation' => 'somethingelse',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['password']);
    }

    // ---------- login() ----------

    public function test_can_login_with_correct_credentials(): void
    {
        User::factory()->create([
            'email' => 'grace@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'grace@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data' => ['user', 'token']]);
    }

    public function test_rejects_a_wrong_password(): void
    {
        User::factory()->create(['email' => 'henry@example.com', 'password' => 'password123']);

        $response = $this->postJson('/api/login', [
            'email' => 'henry@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)->assertJson(['success' => false]);
    }

    public function test_rejects_an_email_that_does_not_exist(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    }

    // ---------- logout() ----------

    public function test_can_logout_and_the_token_stops_working(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $logoutResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout');
        $logoutResponse->assertStatus(200)->assertJson(['success' => true]);

        // The token is genuinely deleted at this point (see below), but
        // within one test method Laravel caches the resolved logged-in
        // user across simulated requests - forgetGuards() clears that
        // cache so the next call actually re-checks the token, the way a
        // real separate request would.
        $this->app['auth']->forgetGuards();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        $reuseResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/reflections');
        $reuseResponse->assertStatus(401);
    }

    public function test_guest_cannot_logout(): void
    {
        $this->postJson('/api/logout')->assertStatus(401);
    }
}
