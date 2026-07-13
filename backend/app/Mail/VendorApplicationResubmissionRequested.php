<?php

namespace App\Mail;

use App\Models\VendorApplication;
use App\Services\BrevoService;

class VendorApplicationResubmissionRequested
{
    public function __construct(private VendorApplication $application, private string $url) {}

    public function send()
    {
        return (new BrevoService())->send(
            $this->application->email,
            $this->application->store_name ?? $this->application->owner_name ?? 'Vendor Applicant',
            'Vendor Application Requires Resubmission',
            view('emails.vendor-application-resubmission-requested', ['application' => $this->application, 'url' => $this->url])->render(),
        );
    }
}
