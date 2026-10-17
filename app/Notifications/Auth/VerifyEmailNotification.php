<?php

namespace App\Notifications\Auth;

use App\Support\SignedFrontendUrl;
use Illuminate\Auth\Notifications\VerifyEmail;

class VerifyEmailNotification extends VerifyEmail
{
    /**
     * Point the email link at the React app, signed & expiring.
     */
    protected function verificationUrl($notifiable): string
    {
        return SignedFrontendUrl::create('/verify-email', [
            'id'   => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
        ], 60);
    }
}