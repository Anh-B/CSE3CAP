<?php

namespace Tests\Feature;

use App\Models\Reflection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers ReflectionController@store and @index
 * (routes: POST /api/reflections, GET /api/reflections)
 */
class ReflectionApiTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Happy path ----------

    public function test_can_submit_a_valid_self_reflection_score(): void
    {
        $response = $this->postJson('/api/reflections', [
            'score'   => 4,
            'comment' => 'Kept the team on schedule this sprint.',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'message', 'data' => ['id', 'score', 'comment']]);

        $this->assertDatabaseHas('reflections', ['score' => 4]);
    }

    public function test_comment_is_optional(): void
    {
        $response = $this->postJson('/api/reflections', ['score' => 3]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('reflections', ['score' => 3, 'comment' => null]);
    }

    public function test_can_list_reflections_most_recent_first(): void
    {
        $older = Reflection::factory()->create(['created_at' => now()->subDay()]);
        $newer = Reflection::factory()->create(['created_at' => now()]);

        $response = $this->getJson('/api/reflections');

        $response->assertStatus(200)->assertJson(['success' => true]);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertEquals($newer->id, $ids->first());
    }

    // ---------- Error handling ----------

    public function test_rejects_missing_score(): void
    {
        $response = $this->postJson('/api/reflections', ['comment' => 'No score given.']);

        $response->assertStatus(422)->assertJsonValidationErrors(['score']);
    }

    public function test_rejects_score_below_minimum(): void
    {
        $response = $this->postJson('/api/reflections', ['score' => 0]);

        $response->assertStatus(422)->assertJsonValidationErrors(['score']);
    }

    public function test_rejects_score_above_maximum(): void
    {
        $response = $this->postJson('/api/reflections', ['score' => 6]);

        $response->assertStatus(422)->assertJsonValidationErrors(['score']);
    }

    public function test_rejects_non_integer_score(): void
    {
        $response = $this->postJson('/api/reflections', ['score' => 'excellent']);

        $response->assertStatus(422)->assertJsonValidationErrors(['score']);
    }

    public function test_rejects_comment_longer_than_1000_characters(): void
    {
        $response = $this->postJson('/api/reflections', [
            'score'   => 3,
            'comment' => str_repeat('a', 1001),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['comment']);
    }

    // ---------- Response performance ----------

    public function test_reflections_index_responds_quickly_with_many_rows(): void
    {
        Reflection::factory()->count(100)->create();

        $start = microtime(true);
        $response = $this->getJson('/api/reflections');
        $elapsedMs = (microtime(true) - $start) * 1000;

        $response->assertStatus(200);
        $this->assertLessThan(500, $elapsedMs, 'GET /api/reflections took too long with 100 rows.');
    }
}
