<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for a real bug: this app is a pure JSON API with no
 * login page, but Laravel's default behaviour is to redirect a logged-out
 * visitor to a route named "login" (for auth failures), or redirect back
 * to the previous page (for validation failures). Since this app has
 * neither a login page nor any page to go "back" to, a plain request
 * that doesn't explicitly ask for JSON - a normal browser, or anything
 * missing an Accept header - crashed or silently redirected instead of
 * getting a clean error response.
 *
 * $this->getJson() / postJson() always send Accept: application/json,
 * so they never actually catch this - that's exactly why it slipped
 * through earlier testing. These use plain $this->get() / post() instead,
 * to genuinely reproduce what a browser or a bare HTTP client sends.
 */
class ApiErrorFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_plain_unauthenticated_request_gets_clean_json_not_a_500(): void
    {
        $response = $this->get('/api/reflections'); // no JSON Accept header, on purpose

        $response->assertStatus(401)->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_a_plain_invalid_registration_gets_clean_json_not_a_redirect(): void
    {
        // Deliberately empty, and sent the plain way, the way a browser
        // or a basic HTTP client would - not through postJson(), which
        // would mask this by asking for JSON itself.
        $response = $this->post('/api/register', []);

        $response->assertStatus(422)->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_the_root_url_still_works_for_a_plain_visitor(): void
    {
        // Not an API route, just confirming the redirectGuestsTo fix
        // didn't break normal, non-API pages.
        $this->get('/')->assertStatus(200);
    }
}
