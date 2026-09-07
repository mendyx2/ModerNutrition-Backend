<?php

namespace App\Services\Notification\Channels;

use App\Services\Notification\SmsWhatsAppService;
use Illuminate\Notifications\Notification;

class SmsWhatsAppChannel
{
    public function __construct(
        protected SmsWhatsAppService $service
    ) {}

    /**
     * Send the given notification.
     */
    public function send(mixed $notifiable, Notification $notification): void
    {
        $phone = $notifiable->phone ?? null;
        if (empty($phone)) {
            return;
        }

        if (method_exists($notification, 'toSms')) {
            $smsMessage = $notification->toSms($notifiable);
            if (!empty($smsMessage)) {
                $this->service->sendSms($phone, $smsMessage);
            }
        }

        if (method_exists($notification, 'toWhatsApp')) {
            $whatsAppMessage = $notification->toWhatsApp($notifiable);
            if (!empty($whatsAppMessage)) {
                $this->service->sendWhatsApp($phone, $whatsAppMessage);
            }
        }
    }
}
