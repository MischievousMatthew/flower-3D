<?php

namespace App\Mail;

use App\Models\VendorApplication;
use App\Services\BrevoService;

class VendorAccountCreated
{
    protected $application;
    protected $password;
    protected $isExistingUser;

    public function __construct(VendorApplication $application, $password = null, $isExistingUser = false)
    {
        $this->application = $application;
        $this->password = $password;
        $this->isExistingUser = $isExistingUser;
    }

    public function send()
    {
        $brevo = new BrevoService();

        $subject = $this->isExistingUser
            ? 'Vendor Application Approved'
            : 'Vendor Account Created';

        $html = view('emails.vendor-account-created', [
            'application' => $this->application,
            'password' => $this->password,
            'isExistingUser' => $this->isExistingUser,
            'loginUrl' => url('http://localhost:5173/guest/login'),
        ])->render();

        return $brevo->send(
            $this->application->email,
            $this->application->store_name ?? 'Vendor',
            $subject,
            $html
        );
    }
}