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

    // ---------- Per-competency scores (radar chart) ----------

    private function validCompetencyScores(): array
    {
        return [
            'contribution'  => 4,
            'communication' => 3,
            'collaboration' => 4,
            'agile'         => 5,
            'continuous'    => 3,
            'leadership'    => 4,
        ];
    }

    public function test_can_submit_a_reflection_with_competency_scores(): void
    {
        $response = $this->postJson('/api/reflections', [
            'score'   => 4,
            'comment' => 'Sprint 4 self review.',
            'scores'  => $this->validCompetencyScores(),
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $reflection = Reflection::latest()->first();
        $this->assertEquals($this->validCompetencyScores(), $reflection->scores);
    }

    public function test_competency_scores_are_optional(): void
    {
        $response = $this->postJson('/api/reflections', ['score' => 3]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('reflections', ['score' => 3]);
        $this->assertNull(Reflection::latest()->first()->scores);
    }

    public function test_rejects_competency_scores_missing_a_competency(): void
    {
        $scores = $this->validCompetencyScores();
        unset($scores['leadership']);

        $response = $this->postJson('/api/reflections', [
            'score'  => 4,
            'scores' => $scores,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['scores.leadership']);
    }

    public function test_rejects_competency_score_out_of_range(): void
    {
        $scores = $this->validCompetencyScores();
        $scores['agile'] = 9;

        $response = $this->postJson('/api/reflections', [
            'score'  => 4,
            'scores' => $scores,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['scores.agile']);
    }

    public function test_can_update_competency_scores(): void
    {
        $reflection = Reflection::factory()->create();

        $response = $this->putJson("/api/reflections/{$reflection->id}", [
            'scores' => $this->validCompetencyScores(),
        ]);

        $response->assertStatus(200);
        $this->assertEquals($this->validCompetencyScores(), $reflection->fresh()->scores);
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

    // ---------- show() ----------

    public function test_can_show_a_single_reflection_with_its_assessments(): void
    {
        $reflection = Reflection::factory()->create([
            'score'  => 4,
            'scores' => $this->validCompetencyScores(),
        ]);

        \App\Models\Assessment::factory()->create([
            'reflection_id' => $reflection->id,
            'score'         => 3,
            'scores'        => [
                'contribution'  => 3,
                'communication' => 4,
                'collaboration' => 3,
                'agile'         => 4,
                'continuous'    => 3,
                'leadership'    => 3,
            ],
        ]);

        $response = $this->getJson("/api/reflections/{$reflection->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', $reflection->id)
            ->assertJsonPath('data.scores.agile', 5)
            ->assertJsonPath('data.assessments.0.scores.communication', 4)
            ->assertJsonCount(1, 'data.assessments');
    }

    public function test_show_returns_empty_assessments_when_none_exist(): void
    {
        $reflection = Reflection::factory()->create();

        $response = $this->getJson("/api/reflections/{$reflection->id}");

        $response->assertStatus(200)->assertJsonCount(0, 'data.assessments');
    }

    public function test_show_returns_404_for_a_nonexistent_reflection(): void
    {
        $response = $this->getJson('/api/reflections/99999');

        $response->assertStatus(404)->assertJson(['success' => false]);
    }

    // ---------- update() ----------

    public function test_can_update_an_existing_reflection(): void
    {
        $reflection = Reflection::factory()->create(['score' => 2, 'comment' => 'Original comment.']);

        $response = $this->putJson("/api/reflections/{$reflection->id}", [
            'score'   => 5,
            'comment' => 'Updated comment.',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'message', 'data' => ['id', 'score', 'comment']]);

        $this->assertDatabaseHas('reflections', [
            'id'      => $reflection->id,
            'score'   => 5,
            'comment' => 'Updated comment.',
        ]);
    }

    public function test_can_update_only_the_score(): void
    {
        $reflection = Reflection::factory()->create(['score' => 1, 'comment' => 'Keep me.']);

        $response = $this->putJson("/api/reflections/{$reflection->id}", ['score' => 4]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('reflections', [
            'id'      => $reflection->id,
            'score'   => 4,
            'comment' => 'Keep me.',
        ]);
    }

    public function test_update_returns_404_for_a_nonexistent_reflection(): void
    {
        $response = $this->putJson('/api/reflections/99999', ['score' => 3]);

        $response->assertStatus(404)->assertJson(['success' => false]);
    }

    public function test_update_rejects_score_out_of_range(): void
    {
        $reflection = Reflection::factory()->create();

        $response = $this->putJson("/api/reflections/{$reflection->id}", ['score' => 6]);

        $response->assertStatus(422)->assertJsonValidationErrors(['score']);
    }

    public function test_update_rejects_non_integer_score(): void
    {
        $reflection = Reflection::factory()->create();

        $response = $this->putJson("/api/reflections/{$reflection->id}", ['score' => 'great']);

        $response->assertStatus(422)->assertJsonValidationErrors(['score']);
    }

    public function test_update_rejects_comment_longer_than_1000_characters(): void
    {
        $reflection = Reflection::factory()->create();

        $response = $this->putJson("/api/reflections/{$reflection->id}", [
            'comment' => str_repeat('c', 1001),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['comment']);
    }

    // ---------- destroy() ----------

    public function test_can_delete_an_existing_reflection(): void
    {
        $reflection = Reflection::factory()->create();

        $response = $this->deleteJson("/api/reflections/{$reflection->id}");

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseMissing('reflections', ['id' => $reflection->id]);
    }

    public function test_delete_returns_404_for_a_nonexistent_reflection(): void
    {
        $response = $this->deleteJson('/api/reflections/99999');

        $response->assertStatus(404)->assertJson(['success' => false]);
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
