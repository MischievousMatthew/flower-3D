<?php

namespace App\Mail;

use App\Models\VendorApplication;
use App\Services\BrevoService;

/**
 * Sent to the applicant right after they apply to sell on BloomCraft:
 * a copy of what they sent and what happens next.
 */
class VendorApplicationReceived
{
    public function __construct(private readonly VendorApplication $application) {}

    /** True when the email service accepted it */
    public function send(): bool
    {
        $html = view('emails.vendor-application-received', ['application' => $this->application])->render();

        $response = (new BrevoService())->send(
            $this->application->email,
            $this->application->owner_name ?: ($this->application->store_name ?: 'Applicant'),
            'We got your BloomCraft application',
            $html
        );

        return $response->successful();
    }
}
