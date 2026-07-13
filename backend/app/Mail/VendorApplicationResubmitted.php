<?php

namespace App\Mail;

use App\Models\VendorApplication;
use App\Services\BrevoService;

class VendorApplicationResubmitted
{
    public function __construct(private VendorApplication $application) {}

    public function send(string $email)
    {
        return (new BrevoService())->send(
            $email,
            'Administrator',
            'Vendor Requirements Resubmitted',
            view('emails.vendor-application-resubmitted', ['application' => $this->application])->render(),
        );
    }
}
