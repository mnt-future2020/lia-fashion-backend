<?php

namespace App\Notifications;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class OtpNotification extends Notification
{
    public $otp;

    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        try {
            Log::info('Sending OTP to: ' . $notifiable->email);

            return (new MailMessage)
                ->subject('Your OTP Verification Code')
                ->line('Hello ' . $notifiable->name . ',')
                ->line('Your OTP is: ' . $this->otp)
                ->line('This OTP will expire in 10 minutes.')
                ->line('If you did not request this, you can ignore this email.')
                ->line('Thanks for using our service!');
        } catch (Exception $e) {
            Log::error('Mail Error: ' . $e->getMessage());
            throw $e;
        }
    }
}