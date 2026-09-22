<?php

namespace Tests\Feature;

use App\Models\Evidence;
use App\Models\Reflection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers EvidenceController
 * (routes: POST/GET /api/reflections/{id}/evidence,
 *          GET /api/evidence/{id}/download, DELETE /api/evidence/{id})
 */
class EvidenceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(); // don't write real files during tests
    }

    // ---------- Happy path ----------

    public function test_can_upload_a_file_as_evidence(): void
    {
        $reflection = Reflection::factory()->create();
        $file = UploadedFile::fake()->create('sprint3-report.pdf', 500, 'application/pdf');

        $response = $this->postJson("/api/reflections/{$reflection->id}/evidence", [
            'file'        => $file,
            'description' => 'Sprint 3 report',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.type', 'file')
            ->assertJsonPath('data.original_name', 'sprint3-report.pdf')
            ->assertJsonPath('data.description', 'Sprint 3 report')
            ->assertJsonMissingPath('data.file_path'); // internal path stays hidden

        $evidence = Evidence::first();
        Storage::assertExists($evidence->file_path);
        $this->assertStringEndsWith("/api/evidence/{$evidence->id}/download", $response->json('data.download_url'));
    }

    public function test_can_add_a_link_as_evidence(): void
    {
        $reflection = Reflection::factory()->create();

        $response = $this->postJson("/api/reflections/{$reflection->id}/evidence", [
            'link'        => 'https://github.com/Anh-B/CSE3CAP/pull/4',
            'description' => 'My PR',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'link')
            ->assertJsonPath('data.link', 'https://github.com/Anh-B/CSE3CAP/pull/4')
            ->assertJsonPath('data.download_url', null);
    }

    public function test_description_is_optional(): void
    {
        $reflection = Reflection::factory()->create();

        $response = $this->postJson("/api/reflections/{$reflection->id}/evidence", [
            'link' => 'https://example.com/doc',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.description', null);
    }

    public function test_can_list_evidence_for_a_reflection(): void
    {
        $reflection = Reflection::factory()->create();
        $other      = Reflection::factory()->create();

        $this->postJson("/api/reflections/{$reflection->id}/evidence", ['link' => 'https://example.com/a']);
        $this->postJson("/api/reflections/{$reflection->id}/evidence", ['file' => UploadedFile::fake()->create('screenshot.png', 200, 'image/png')]);
        $this->postJson("/api/reflections/{$other->id}/evidence", ['link' => 'https://example.com/other']);

        $response = $this->getJson("/api/reflections/{$reflection->id}/evidence");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data');
    }

    public function test_single_reflection_includes_its_evidence(): void
    {
        $reflection = Reflection::factory()->create();
        $this->postJson("/api/reflections/{$reflection->id}/evidence", ['link' => 'https://example.com/a']);

        $response = $this->getJson("/api/reflections/{$reflection->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.evidence')
            ->assertJsonPath('data.evidence.0.link', 'https://example.com/a');
    }

    public function test_can_download_an_uploaded_file_with_its_original_name(): void
    {
        $reflection = Reflection::factory()->create();
        $this->postJson("/api/reflections/{$reflection->id}/evidence", [
            'file' => UploadedFile::fake()->createWithContent('notes.txt', 'my sprint notes'),
        ]);
        $evidence = Evidence::first();

        $response = $this->get("/api/evidence/{$evidence->id}/download");

        $response->assertStatus(200)->assertDownload('notes.txt');
        $this->assertSame('my sprint notes', $response->streamedContent());
    }

    public function test_can_delete_evidence_and_its_file(): void
    {
        $reflection = Reflection::factory()->create();
        $this->postJson("/api/reflections/{$reflection->id}/evidence", [
            'file' => UploadedFile::fake()->create('draft.docx', 100),
        ]);
        $evidence = Evidence::first();
        $path = $evidence->file_path;

        $response = $this->deleteJson("/api/evidence/{$evidence->id}");

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertDatabaseMissing('evidence', ['id' => $evidence->id]);
        Storage::assertMissing($path);
    }

    public function test_deleting_a_reflection_also_deletes_its_evidence_files(): void
    {
        $reflection = Reflection::factory()->create();
        $this->postJson("/api/reflections/{$reflection->id}/evidence", [
            'file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
        ]);
        $path = Evidence::first()->file_path;

        $this->deleteJson("/api/reflections/{$reflection->id}")->assertStatus(200);

        $this->assertDatabaseCount('evidence', 0);
        Storage::assertMissing($path);
    }

    // ---------- Error handling ----------

    public function test_rejects_when_neither_file_nor_link_is_sent(): void
    {
        $reflection = Reflection::factory()->create();

        $response = $this->postJson("/api/reflections/{$reflection->id}/evidence", ['description' => 'Nothing attached']);

        $response->assertStatus(422)->assertJsonValidationErrors(['file', 'link']);
    }

    public function test_rejects_when_both_file_and_link_are_sent(): void
    {
        $reflection = Reflection::factory()->create();

        $response = $this->postJson("/api/reflections/{$reflection->id}/evidence", [
            'file' => UploadedFile::fake()->create('report.pdf', 100, 'application/pdf'),
            'link' => 'https://example.com/doc',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['file']);
        $this->assertDatabaseCount('evidence', 0);
    }

    public function test_rejects_a_file_type_that_is_not_allowed(): void
    {
        $reflection = Reflection::factory()->create();

        $response = $this->postJson("/api/reflections/{$reflection->id}/evidence", [
            'file' => UploadedFile::fake()->create('setup.exe', 100, 'application/x-msdownload'),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['file']);
    }

    public function test_rejects_a_renamed_file_even_if_the_content_looks_safe(): void
    {
        $reflection = Reflection::factory()->create();

        // Plain text inside, but named .exe - should still be rejected
        $response = $this->postJson("/api/reflections/{$reflection->id}/evidence", [
            'file' => UploadedFile::fake()->createWithContent('setup.exe', 'just some text'),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['file']);
        $this->assertDatabaseCount('evidence', 0);
    }

    public function test_rejects_a_file_over_10mb(): void
    {
        $reflection = Reflection::factory()->create();

        $response = $this->postJson("/api/reflections/{$reflection->id}/evidence", [
            'file' => UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf'),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['file']);
    }

    public function test_rejects_a_link_that_is_not_http_or_https(): void
    {
        $reflection = Reflection::factory()->create();

        foreach (['not a link', 'javascript:alert(1)', 'ftp://example.com/file'] as $bad) {
            $this->postJson("/api/reflections/{$reflection->id}/evidence", ['link' => $bad])
                ->assertStatus(422)
                ->assertJsonValidationErrors(['link']);
        }
    }

    public function test_rejects_description_longer_than_255_characters(): void
    {
        $reflection = Reflection::factory()->create();

        $response = $this->postJson("/api/reflections/{$reflection->id}/evidence", [
            'link'        => 'https://example.com/doc',
            'description' => str_repeat('d', 256),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['description']);
    }

    public function test_upload_returns_404_for_a_nonexistent_reflection(): void
    {
        $response = $this->postJson('/api/reflections/99999/evidence', ['link' => 'https://example.com/doc']);

        $response->assertStatus(404)->assertJson(['success' => false]);
    }

    public function test_list_returns_404_for_a_nonexistent_reflection(): void
    {
        $this->getJson('/api/reflections/99999/evidence')->assertStatus(404)->assertJson(['success' => false]);
    }

    public function test_download_returns_404_for_a_link_or_missing_evidence(): void
    {
        $reflection = Reflection::factory()->create();
        $this->postJson("/api/reflections/{$reflection->id}/evidence", ['link' => 'https://example.com/doc']);
        $link = Evidence::first();

        $this->getJson("/api/evidence/{$link->id}/download")->assertStatus(404)->assertJson(['success' => false]);
        $this->getJson('/api/evidence/99999/download')->assertStatus(404)->assertJson(['success' => false]);
    }

    public function test_delete_returns_404_for_nonexistent_evidence(): void
    {
        $this->deleteJson('/api/evidence/99999')->assertStatus(404)->assertJson(['success' => false]);
    }
}
