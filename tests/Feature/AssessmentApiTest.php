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

    // ---------- Per-competency counter-scores (radar chart) ----------

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

    public function test_can_submit_an_assessment_with_competency_scores(): void
    {
        $reflection = Reflection::factory()->create();

        $response = $this->postJson('/api/assessments', [
            'reflection_id' => $reflection->id,
            'score'         => 4,
            'feedback'      => 'Sprint 4 assessor review.',
            'scores'        => $this->validCompetencyScores(),
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $assessment = \App\Models\Assessment::latest()->first();
        $this->assertEquals($this->validCompetencyScores(), $assessment->scores);
    }

    public function test_assessment_competency_scores_are_optional(): void
    {
        $reflection = Reflection::factory()->create();

        $response = $this->postJson('/api/assessments', [
            'reflection_id' => $reflection->id,
            'score'         => 3,
        ]);

        $response->assertStatus(201);
        $this->assertNull(\App\Models\Assessment::latest()->first()->scores);
    }

    public function test_rejects_assessment_competency_scores_missing_a_competency(): void
    {
        $reflection = Reflection::factory()->create();
        $scores = $this->validCompetencyScores();
        unset($scores['leadership']);

        $response = $this->postJson('/api/assessments', [
            'reflection_id' => $reflection->id,
            'score'         => 4,
            'scores'        => $scores,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['scores.leadership']);
    }

    public function test_rejects_assessment_competency_score_out_of_range(): void
    {
        $reflection = Reflection::factory()->create();
        $scores = $this->validCompetencyScores();
        $scores['agile'] = 9;

        $response = $this->postJson('/api/assessments', [
            'reflection_id' => $reflection->id,
            'score'         => 4,
            'scores'        => $scores,
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['scores.agile']);
    }

    // ---------- index() / show() ----------

    public function test_can_list_assessments(): void
    {
        \App\Models\Assessment::factory()->count(3)->create();

        $response = $this->getJson('/api/assessments');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.total', 3);
    }

    public function test_can_filter_assessments_by_reflection(): void
    {
        $reflection = Reflection::factory()->create();
        \App\Models\Assessment::factory()->count(2)->create(['reflection_id' => $reflection->id]);
        \App\Models\Assessment::factory()->create(); // belongs to a different reflection

        $response = $this->getJson("/api/assessments?reflection_id={$reflection->id}");

        $response->assertStatus(200)->assertJsonCount(2, 'data');
        $this->assertTrue(
            collect($response->json('data'))->every(fn ($a) => $a['reflection_id'] === $reflection->id)
        );
    }

    public function test_can_show_a_single_assessment_with_its_scores(): void
    {
        $assessment = \App\Models\Assessment::factory()->create([
            'scores' => $this->validCompetencyScores(),
        ]);

        $response = $this->getJson("/api/assessments/{$assessment->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.id', $assessment->id)
            ->assertJsonPath('data.scores.leadership', 4);
    }

    public function test_show_returns_404_for_a_nonexistent_assessment(): void
    {
        $response = $this->getJson('/api/assessments/99999');

        $response->assertStatus(404)->assertJson(['success' => false]);
    }

    public function test_deleting_a_reflection_also_deletes_its_assessments(): void
    {
        $reflection = Reflection::factory()->create();
        $assessment = \App\Models\Assessment::factory()->create(['reflection_id' => $reflection->id]);

        $this->deleteJson("/api/reflections/{$reflection->id}")->assertStatus(200);

        $this->assertDatabaseMissing('assessments', ['id' => $assessment->id]);
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
