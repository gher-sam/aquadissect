<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class StudentSetupOtp extends Mailable
{
    public function __construct(public string $otp) {}

    public function build(): static
    {
        return $this->subject('Your AquaDissect email verification code')
            ->view('emails.student-setup-otp');
    }
}
