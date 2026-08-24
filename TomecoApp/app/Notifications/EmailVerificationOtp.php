<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationOtp extends Notification
{
    use Queueable;

    public function __construct(private readonly string $otp) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your TOMECO email verification code')
            ->greeting('Hello '.$notifiable->fullName.',')
            ->line('Use this verification code to confirm your email address:')
            ->line($this->otp)
            ->line('This code expires in 10 minutes. If you did not create this account, you can ignore this email.');
    }
}
