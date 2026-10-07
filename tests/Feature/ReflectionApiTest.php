<?php

namespace Tests\Feature;

use App\Models\Reflection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers ReflectionController@store and @index
 * (routes: POST /api/reflections, GET /api/reflections)
 */
class ReflectionApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Sprint 5 - every route now needs a logged-in user. This logs one
        // in for every test in this file. $this->user is the one it uses,
        // so tests that update/delete a reflection can create it owned by
        // this same user - otherwise the new ownership check fails them.
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    // Logs in an assessor instead of the default student from setUp().
    private function actAsAssessor(): User
    {
        $assessor = User::factory()->assessor()->create();
        Sanctum::actingAs($assessor);

        return $assessor;
    }

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
        $reflection = Reflection::factory()->create(['user_id' => $this->user->id]);

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
        $this->actAsAssessor(); // assessors see everyone's reflections
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

    public function test_rejects_comment_longer_than_5000_characters(): void
    {
        $response = $this->postJson('/api/reflections', [
            'score'   => 3,
            'comment' => str_repeat('a', 5001),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['comment']);
    }

    // ---------- show() ----------

    public function test_can_show_a_single_reflection_with_its_assessments(): void
    {
        $this->actAsAssessor(); // assessors see everyone's reflections
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
        $this->actAsAssessor(); // assessors see everyone's reflections
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
        $reflection = Reflection::factory()->create(['user_id' => $this->user->id, 'score' => 2, 'comment' => 'Original comment.']);

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
        $reflection = Reflection::factory()->create(['user_id' => $this->user->id, 'score' => 1, 'comment' => 'Keep me.']);

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
        $reflection = Reflection::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/api/reflections/{$reflection->id}", ['score' => 6]);

        $response->assertStatus(422)->assertJsonValidationErrors(['score']);
    }

    public function test_update_rejects_non_integer_score(): void
    {
        $reflection = Reflection::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/api/reflections/{$reflection->id}", ['score' => 'great']);

        $response->assertStatus(422)->assertJsonValidationErrors(['score']);
    }

    public function test_update_rejects_comment_longer_than_5000_characters(): void
    {
        $reflection = Reflection::factory()->create(['user_id' => $this->user->id]);

        $response = $this->putJson("/api/reflections/{$reflection->id}", [
            'comment' => str_repeat('c', 5001),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['comment']);
    }

    // ---------- destroy() ----------

    public function test_can_delete_an_existing_reflection(): void
    {
        $reflection = Reflection::factory()->create(['user_id' => $this->user->id]);

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

    // ---------- Sprint 6: assessed vs pending ----------

    public function test_list_shows_whether_each_reflection_is_assessed_or_pending(): void
    {
        $this->actAsAssessor(); // assessors see everyone's reflections
        $assessed = Reflection::factory()->create();
        $pending  = Reflection::factory()->create();
        \App\Models\Assessment::factory()->create(['reflection_id' => $assessed->id]);

        $response = $this->getJson('/api/reflections');

        $response->assertStatus(200);
        $byId = collect($response->json('data'))->keyBy('id');
        $this->assertSame('assessed', $byId[$assessed->id]['assessment_status']);
        $this->assertSame('pending',  $byId[$pending->id]['assessment_status']);
        $this->assertArrayNotHasKey('assessments_exists', $byId[$pending->id]);
    }

    public function test_can_filter_to_only_pending_reflections(): void
    {
        $this->actAsAssessor(); // assessors see everyone's reflections
        $assessed = Reflection::factory()->create();
        Reflection::factory()->count(2)->create();
        \App\Models\Assessment::factory()->create(['reflection_id' => $assessed->id]);

        $response = $this->getJson('/api/reflections?status=pending');

        $response->assertStatus(200)->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
        $this->assertTrue(collect($response->json('data'))->every(fn ($r) => $r['assessment_status'] === 'pending'));
    }

    public function test_can_filter_to_only_assessed_reflections(): void
    {
        $this->actAsAssessor(); // assessors see everyone's reflections
        $assessed = Reflection::factory()->create();
        Reflection::factory()->count(2)->create();
        \App\Models\Assessment::factory()->count(2)->create(['reflection_id' => $assessed->id]);

        $response = $this->getJson('/api/reflections?status=assessed');

        // Two assessments on one reflection still counts as one reflection
        $response->assertStatus(200)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $assessed->id);
    }

    public function test_rejects_an_invalid_status_filter(): void
    {
        $this->getJson('/api/reflections?status=done')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_single_reflection_says_pending_before_it_is_assessed(): void
    {
        $this->actAsAssessor(); // assessors see everyone's reflections
        $reflection = Reflection::factory()->create();

        $this->getJson("/api/reflections/{$reflection->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.assessment_status', 'pending');
    }

    public function test_single_reflection_says_assessed_once_it_has_a_score(): void
    {
        $this->actAsAssessor(); // assessors see everyone's reflections
        $reflection = Reflection::factory()->create();
        \App\Models\Assessment::factory()->create(['reflection_id' => $reflection->id]);

        $this->getJson("/api/reflections/{$reflection->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.assessment_status', 'assessed');
    }

    // ---------- Sprint 5: authentication & ownership ----------

    public function test_guest_cannot_create_a_reflection(): void
    {
        $this->app['auth']->forgetGuards(); // undo setUp()'s login - simulates a logged-out request

        $response = $this->postJson('/api/reflections', ['score' => 4]);

        $response->assertStatus(401);
    }

    public function test_guest_cannot_list_reflections(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/reflections')->assertStatus(401);
    }

    public function test_cannot_update_another_students_reflection(): void
    {
        $someoneElse = User::factory()->create();
        $reflection = Reflection::factory()->create(['user_id' => $someoneElse->id]);

        $response = $this->putJson("/api/reflections/{$reflection->id}", ['score' => 5]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('reflections', ['id' => $reflection->id, 'score' => $reflection->score]);
    }

    public function test_cannot_delete_another_students_reflection(): void
    {
        $someoneElse = User::factory()->create();
        $reflection = Reflection::factory()->create(['user_id' => $someoneElse->id]);

        $response = $this->deleteJson("/api/reflections/{$reflection->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('reflections', ['id' => $reflection->id]);
    }

    public function test_reflections_index_responds_quickly_with_many_rows(): void
    {
        Reflection::factory()->count(100)->create();

        $start = microtime(true);
        $response = $this->getJson('/api/reflections');
        $elapsedMs = (microtime(true) - $start) * 1000;

        $response->assertStatus(200);
        $this->assertLessThan(500, $elapsedMs, 'GET /api/reflections took too long with 100 rows.');
    }

    // ---------- Journal entries saved on the server ----------

    public function test_can_save_title_and_category_with_a_reflection(): void
    {
        $response = $this->postJson('/api/reflections', [
            'score'     => 4,
            'comment'   => 'Learned a lot about estimating.',
            'gig_title' => 'Sprint 3 retro',
            'category'  => 'Development',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.gig_title', 'Sprint 3 retro')
            ->assertJsonPath('data.category', 'Development');
        $this->assertDatabaseHas('reflections', [
            'user_id'   => $this->user->id,
            'gig_title' => 'Sprint 3 retro',
            'category'  => 'Development',
        ]);
    }

    public function test_title_and_category_are_optional(): void
    {
        $this->postJson('/api/reflections', ['score' => 3])
            ->assertStatus(201)
            ->assertJsonPath('data.gig_title', null);
    }

    public function test_can_update_title_and_category(): void
    {
        $reflection = Reflection::factory()->create(['user_id' => $this->user->id, 'gig_title' => 'Old']);

        $this->putJson("/api/reflections/{$reflection->id}", [
            'gig_title' => 'New title',
            'category'  => 'Research',
        ])->assertStatus(200)->assertJsonPath('data.gig_title', 'New title');
    }

    public function test_rejects_a_title_longer_than_255_characters(): void
    {
        $this->postJson('/api/reflections', ['score' => 3, 'gig_title' => str_repeat('a', 256)])
            ->assertStatus(422)->assertJsonValidationErrors(['gig_title']);
    }

    public function test_a_long_reflection_up_to_5000_characters_is_accepted(): void
    {
        $this->postJson('/api/reflections', ['score' => 3, 'comment' => str_repeat('a', 5000)])
            ->assertStatus(201);
    }

    public function test_each_save_without_an_id_creates_a_separate_reflection(): void
    {
        $this->postJson('/api/reflections', ['score' => 3, 'gig_title' => 'First']);
        $this->postJson('/api/reflections', ['score' => 4, 'gig_title' => 'Second']);

        $this->assertSame(2, Reflection::where('user_id', $this->user->id)->count());
    }

    // ---------- Who can see which reflections ----------

    public function test_a_student_only_lists_their_own_reflections(): void
    {
        $mine = Reflection::factory()->create(['user_id' => $this->user->id, 'gig_title' => 'Mine']);
        Reflection::factory()->count(2)->create(); // belong to other students

        $response = $this->getJson('/api/reflections');

        $response->assertStatus(200)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_the_list_includes_title_category_and_evidence(): void
    {
        $mine = Reflection::factory()->create([
            'user_id' => $this->user->id, 'gig_title' => 'Mine', 'category' => 'Other',
        ]);
        \App\Models\Evidence::create([
            'reflection_id' => $mine->id, 'type' => 'link', 'link' => 'https://example.com/a',
        ]);

        $this->getJson('/api/reflections')
            ->assertStatus(200)
            ->assertJsonPath('data.0.gig_title', 'Mine')
            ->assertJsonPath('data.0.category', 'Other')
            ->assertJsonCount(1, 'data.0.evidence');
    }

    public function test_an_assessor_lists_everyones_reflections(): void
    {
        Reflection::factory()->count(3)->create();
        $this->actAsAssessor();

        $this->getJson('/api/reflections')->assertStatus(200)->assertJsonCount(3, 'data');
    }

    public function test_an_assessor_can_ask_for_only_their_own_reflections(): void
    {
        Reflection::factory()->count(3)->create();
        $assessor = $this->actAsAssessor();
        $own = Reflection::factory()->create(['user_id' => $assessor->id]);

        $this->getJson('/api/reflections?mine=1')
            ->assertStatus(200)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
    }

    public function test_the_status_filter_still_only_covers_a_students_own_reflections(): void
    {
        Reflection::factory()->count(2)->create(); // other students, pending
        $mine = Reflection::factory()->create(['user_id' => $this->user->id]);

        $this->getJson('/api/reflections?status=pending')
            ->assertStatus(200)->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
    }

    public function test_a_student_can_show_their_own_reflection(): void
    {
        $mine = Reflection::factory()->create(['user_id' => $this->user->id]);

        $this->getJson("/api/reflections/{$mine->id}")->assertStatus(200)->assertJsonPath('data.id', $mine->id);
    }

    public function test_a_student_cannot_show_another_students_reflection(): void
    {
        $theirs = Reflection::factory()->create();

        $this->getJson("/api/reflections/{$theirs->id}")->assertStatus(403)->assertJson(['success' => false]);
    }

    public function test_an_assessor_can_show_any_reflection(): void
    {
        $theirs = Reflection::factory()->create();
        $this->actAsAssessor();

        $this->getJson("/api/reflections/{$theirs->id}")->assertStatus(200)->assertJsonPath('data.id', $theirs->id);
    }

    // ---------- PDF export ----------

    public function test_the_pdf_view_shows_the_entry_title_and_category(): void
    {
        $reflection = Reflection::factory()->create([
            'user_id' => $this->user->id, 'gig_title' => 'Sprint 3 retro', 'category' => 'Development',
        ]);

        $html = view('journal.export', ['reflections' => collect([$reflection])])->render();

        $this->assertStringContainsString('Sprint 3 retro', $html);
        $this->assertStringContainsString('Development', $html);
    }

    public function test_the_pdf_export_returns_a_pdf_file(): void
    {
        Reflection::factory()->create(['user_id' => $this->user->id, 'gig_title' => 'Mine']);
        Reflection::factory()->create(['gig_title' => 'Someone elses']);

        $response = $this->get('/api/journal/export');

        $response->assertStatus(200);
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
