<?php

namespace Tests\Feature;

use App\Models\Reflection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers AssessmentController@store (route: POST /api/assessments)
 */
class AssessmentApiTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Happy path ----------

    public function test_can_submit_a_valid_assessment(): void
    {
        $reflection = Reflection::factory()->create();

        $response = $this->postJson('/api/assessments', [
            'reflection_id' => $reflection->id,
            'score'         => 4,
            'feedback'      => 'Good progress, keep it up.',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'message', 'data' => ['id', 'reflection_id', 'score']]);

        $this->assertDatabaseHas('assessments', [
            'reflection_id' => $reflection->id,
            'score'         => 4,
        ]);
    }

    public function test_feedback_is_optional(): void
    {
        $reflection = Reflection::factory()->create();

        $response = $this->postJson('/api/assessments', [
            'reflection_id' => $reflection->id,
            'score'         => 5,
        ]);

        $response->assertStatus(201);
    }

    // ---------- Error handling ----------

    public function test_rejects_missing_reflection_id(): void
    {
        $response = $this->postJson('/api/assessments', ['score' => 4]);

        $response->assertStatus(422)->assertJsonValidationErrors(['reflection_id']);
    }

    public function test_rejects_reflection_id_that_does_not_exist(): void
    {
        $response = $this->postJson('/api/assessments', [
            'reflection_id' => 99999,
            'score'         => 4,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['reflection_id']);
    }

    public function test_rejects_missing_score(): void
    {
        $reflection = Reflection::factory()->create();

        $response = $this->postJson('/api/assessments', ['reflection_id' => $reflection->id]);

        $response->assertStatus(422)->assertJsonValidationErrors(['score']);
    }

    public function test_rejects_score_out_of_range(): void
    {
        $reflection = Reflection::factory()->create();

        $response = $this->postJson('/api/assessments', [
            'reflection_id' => $reflection->id,
            'score'         => 7,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['score']);
    }

    public function test_rejects_feedback_longer_than_1000_characters(): void
    {
        $reflection = Reflection::factory()->create();

        $response = $this->postJson('/api/assessments', [
            'reflection_id' => $reflection->id,
            'score'         => 3,
            'feedback'      => str_repeat('b', 1001),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['feedback']);
    }
}
