<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The backend is API-only (there's no page at "/"), so this smoke test checks
 * the API is up and its token sign-in guards protected routes.
 */
class ExampleTest extends TestCase
{
    public function test_protected_api_routes_require_a_token(): void
    {
        $this->getJson('/api/vendor/profile')
            ->assertStatus(401)
            ->assertJson(['success' => false, 'message' => 'Unauthenticated']);
    }
}
