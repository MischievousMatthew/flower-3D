<?php

namespace App\Mail;

use App\Models\VendorApplication;
use App\Services\BrevoService;

class VendorApplicationRejected
{
    protected VendorApplication $application;
    protected string $rejectionReason;

    public function __construct(VendorApplication $application, string $rejectionReason)
    {
        $this->application = $application;
        $this->rejectionReason = $rejectionReason;
    }

    public function send()
    {
        $brevo = new BrevoService();

        $html = view('emails.vendor-application-rejected', [
            'application'     => $this->application,
            'rejectionReason' => $this->rejectionReason,
            'supportEmail'    => config('mail.from.address', 'support@bloomcraft.app'),
        ])->render();

        return $brevo->send(
            $this->application->email,
            $this->application->store_name ?? $this->application->owner_name ?? 'Vendor Applicant',
            'Vendor Application Update',
            $html
        );
    }
}
