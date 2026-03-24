<?php

namespace App\Mail;

use App\Models\VendorApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VendorAccountCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $application;
    public $password;
    public $isExistingUser;

    /**
     * Create a new message instance.
     */
    public function __construct(VendorApplication $application, $password = null, $isExistingUser = false)
    {
        $this->application = $application;
        $this->password = $password;
        $this->isExistingUser = $isExistingUser;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $subject = $this->isExistingUser 
            ? 'Your Vendor Application Has Been Approved'
            : 'Your Vendor Account Has Been Created';
            
        return $this->subject($subject)
                    ->view('emails.vendor-account-created')
                    ->with([
                        'application' => $this->application,
                        'password' => $this->password,
                        'isExistingUser' => $this->isExistingUser,
                        'loginUrl' => url('http://localhost:5173/guest/login'),
                    ]);
    }
}