<?php

namespace App\Notifications\Employee;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

class EmployeeInvitedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Reuses the password broker so the existing reset-password
        // endpoint + frontend page complete the onboarding.
        $token = Password::createToken($notifiable);

        $url = rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
            'token'  => $token,
            'email'  => $notifiable->getEmailForPasswordReset(),
            'invite' => '1',
        ]);

        return (new MailMessage)
            ->subject('Welcome to PrimeIT Workspace')
            ->greeting('Welcome aboard!')
            ->line('A PrimeIT Workspace account has been created for you.')
            ->line('Use the button below to choose your password and activate your account.')
            ->action('Choose your password', $url)
            ->line('This link expires in 60 minutes — you can request a new one from the sign-in page.')
            ->line('If you were not expecting this invitation, you can safely ignore this email.');
    }
}