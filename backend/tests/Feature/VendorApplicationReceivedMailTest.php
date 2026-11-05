<?php

namespace Tests\Feature;

use App\Mail\VendorApplicationReceived;
use App\Models\VendorApplication;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VendorApplicationReceivedMailTest extends TestCase
{
    private function application(): VendorApplication
    {
        return (new VendorApplication())->forceFill([
            'application_id' => 'VEN-ABC-202611050001',
            'store_name' => 'Petal & Stem',
            'owner_name' => 'Maria Santos',
            'email' => 'maria@example.com',
        ]);
    }

    public function test_the_applicant_gets_a_copy_of_their_application(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 'x'], 201)]);

        $this->assertTrue((new VendorApplicationReceived($this->application()))->send());

        Http::assertSent(function (Request $request) {
            $html = $request['htmlContent'];

            return $request['to'][0]['email'] === 'maria@example.com'
                && str_contains($html, 'Petal &amp; Stem')
                && str_contains($html, 'Hi Maria')
                && str_contains($html, 'What happens next')
                && ! str_contains($html, 'VEN-'); // no application code
        });
    }

    public function test_a_failed_send_is_reported(): void
    {
        Http::fake(['api.brevo.com/*' => Http::response(['message' => 'Key not found'], 401)]);

        $this->assertFalse((new VendorApplicationReceived($this->application()))->send());
    }
}
