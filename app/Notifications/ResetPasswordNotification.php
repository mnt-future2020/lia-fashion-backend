<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends Notification
{
    private $token;

    public function __construct($token)
    {
        $this->token = $token;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        // Create the reset URL with base URL from environment
        $baseUrl = rtrim(env('FRONTEND_URL', 'http://localhost:3000'), '/');
        $resetUrl = $baseUrl . '/reset-password?' . http_build_query([
            'token' => $this->token,
            'email' => $notifiable->email
        ]);

        return (new MailMessage)
            ->subject('Reset Password Notification')
            ->markdown('mail.reset-password', ['url' => $resetUrl]);
        }
    }

