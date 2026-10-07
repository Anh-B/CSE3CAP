<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers `php artisan assessor:create` - the only way to make an assessor
 * account now that registration always creates students.
 */
class CreateAssessorCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_new_assessor_account(): void
    {
        $this->artisan('assessor:create', ['email' => 'jane@example.com', 'name' => 'Jane Smith'])
            ->expectsQuestion('Password (min 8 characters)', 'password123')
            ->expectsOutput('Assessor account created for jane@example.com.')
            ->assertExitCode(0);

        $user = User::where('email', 'jane@example.com')->first();
        $this->assertSame('assessor', $user->role);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_the_new_assessor_can_log_in_and_assess(): void
    {
        $this->artisan('assessor:create', ['email' => 'jane@example.com', 'name' => 'Jane Smith'])
            ->expectsQuestion('Password (min 8 characters)', 'password123')
            ->assertExitCode(0);

        $token = $this->postJson('/api/login', ['email' => 'jane@example.com', 'password' => 'password123'])
            ->assertStatus(200)
            ->json('data.token');

        $reflection = \App\Models\Reflection::factory()->create();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/assessments', ['reflection_id' => $reflection->id, 'score' => 4])
            ->assertStatus(201);
    }

    public function test_upgrades_an_existing_student_to_assessor(): void
    {
        User::factory()->create(['email' => 'sam@example.com']);

        $this->artisan('assessor:create', ['email' => 'sam@example.com'])
            ->expectsConfirmation('sam@example.com already exists as a student. Upgrade it to assessor?', 'yes')
            ->expectsOutput('sam@example.com is now an assessor.')
            ->assertExitCode(0);

        $this->assertSame('assessor', User::where('email', 'sam@example.com')->value('role'));
    }

    public function test_rejects_a_short_password(): void
    {
        $this->artisan('assessor:create', ['email' => 'jane@example.com', 'name' => 'Jane Smith'])
            ->expectsQuestion('Password (min 8 characters)', 'short')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'jane@example.com']);
    }
}
