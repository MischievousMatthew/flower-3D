<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Only BloomCraft's own sites may call the API from a browser. */
class CorsOriginsTest extends TestCase
{
    private function preflight(string $origin)
    {
        return $this->call('OPTIONS', '/api/site/settings', [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);
    }

    public function test_the_live_site_and_local_development_are_allowed(): void
    {
        foreach (['https://bloomcraft-app.vercel.app', 'http://localhost:5173'] as $origin) {
            $this->assertSame($origin, $this->preflight($origin)->headers->get('Access-Control-Allow-Origin'), $origin);
        }
    }

    public function test_someone_elses_vercel_site_is_not(): void
    {
        foreach (['https://evil-bloomcraft.vercel.app', 'https://bloomcraft-app.vercel.app.evil.com', 'https://example.com'] as $origin) {
            $this->assertNull($this->preflight($origin)->headers->get('Access-Control-Allow-Origin'), $origin);
        }
    }
}
