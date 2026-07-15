<?php

namespace App\Notifications\Channels;

use App\Services\SmsService;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(
        protected SmsService $sms
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $message = $notification->toSms($notifiable);
        if (! is_string($message) || trim($message) === '') {
            return;
        }

        $to = $notifiable->routeNotificationFor('sms', $notification)
            ?? (property_exists($notifiable, 'phone') ? $notifiable->phone : null)
            ?? (method_exists($notifiable, 'getPhoneForSms') ? $notifiable->getPhoneForSms() : null);

        $this->sms->send(is_string($to) ? $to : null, $message);
    }
}
