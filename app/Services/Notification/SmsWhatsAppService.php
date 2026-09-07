<?php

namespace App\Services\Notification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsWhatsAppService
{
    /**
     * Send an SMS message.
     */
    public function sendSms(string $toPhone, string $message): bool
    {
        $driver = config('services.sms.driver', env('SMS_DRIVER', 'log'));

        $cleanedPhone = $this->formatPhoneNumber($toPhone);
        if (empty($cleanedPhone)) {
            Log::warning("SmsWhatsAppService: Invalid phone number passed: {$toPhone}");
            return false;
        }

        if ($driver === 'log' || app()->environment('local', 'testing')) {
            Log::info("[SMS SIMULATION] To: {$cleanedPhone} | Message: {$message}");
            return true;
        }

        // Generic HTTP Webhook / SMS Gateway provider
        if ($driver === 'generic_http') {
            try {
                $endpoint = env('SMS_GATEWAY_URL');
                $apiKey = env('SMS_GATEWAY_KEY');
                if (!$endpoint) {
                    Log::warning("[SMS GATEWAY] No SMS_GATEWAY_URL configured, logging message: {$message}");
                    return true;
                }

                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type'  => 'application/json',
                ])->timeout(10)->post($endpoint, [
                    'to'      => $cleanedPhone,
                    'message' => $message,
                    'from'    => env('SMS_SENDER_ID', 'ModerNutri'),
                ]);

                return $response->successful();
            } catch (\Throwable $e) {
                Log::error("[SMS GATEWAY ERROR] Failed to send SMS to {$cleanedPhone}: " . $e->getMessage());
                return false;
            }
        }

        // Twilio driver
        if ($driver === 'twilio') {
            try {
                $sid = env('TWILIO_SID');
                $token = env('TWILIO_AUTH_TOKEN');
                $from = env('TWILIO_NUMBER');

                if (!$sid || !$token || !$from) {
                    Log::info("[SMS TWILIO FALLBACK] To: {$cleanedPhone} | Message: {$message}");
                    return true;
                }

                $response = Http::withBasicAuth($sid, $token)
                    ->asForm()
                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                        'To'   => $cleanedPhone,
                        'From' => $from,
                        'Body' => $message,
                    ]);

                return $response->successful();
            } catch (\Throwable $e) {
                Log::error("[TWILIO SMS ERROR] Failed to send to {$cleanedPhone}: " . $e->getMessage());
                return false;
            }
        }

        Log::info("[SMS DEFAULT LOG] To: {$cleanedPhone} | Message: {$message}");
        return true;
    }

    /**
     * Send a WhatsApp message.
     */
    public function sendWhatsApp(string $toPhone, string $message): bool
    {
        $driver = config('services.whatsapp.driver', env('WHATSAPP_DRIVER', 'log'));

        $cleanedPhone = $this->formatPhoneNumber($toPhone);
        if (empty($cleanedPhone)) {
            Log::warning("SmsWhatsAppService: Invalid WhatsApp phone number: {$toPhone}");
            return false;
        }

        if ($driver === 'log' || app()->environment('local', 'testing')) {
            Log::info("[WHATSAPP SIMULATION] To: {$cleanedPhone} | Message: {$message}");
            return true;
        }

        // Twilio WhatsApp driver
        if ($driver === 'twilio') {
            try {
                $sid = env('TWILIO_SID');
                $token = env('TWILIO_AUTH_TOKEN');
                $from = env('TWILIO_WHATSAPP_NUMBER', 'whatsapp:+14155238886');

                if (!$sid || !$token) {
                    Log::info("[WHATSAPP TWILIO FALLBACK] To: whatsapp:{$cleanedPhone} | Message: {$message}");
                    return true;
                }

                $response = Http::withBasicAuth($sid, $token)
                    ->asForm()
                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                        'To'   => "whatsapp:{$cleanedPhone}",
                        'From' => $from,
                        'Body' => $message,
                    ]);

                return $response->successful();
            } catch (\Throwable $e) {
                Log::error("[TWILIO WHATSAPP ERROR] Failed to send to {$cleanedPhone}: " . $e->getMessage());
                return false;
            }
        }

        Log::info("[WHATSAPP DEFAULT LOG] To: {$cleanedPhone} | Message: {$message}");
        return true;
    }

    /**
     * Format phone number to international E.164 standard.
     */
    public function formatPhoneNumber(?string $phone, string $defaultCountryCode = '+243'): string
    {
        if (empty($phone)) {
            return '';
        }

        $phone = preg_replace('/[^\d+]/', '', trim($phone));

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        if (str_starts_with($phone, '00')) {
            return '+' . substr($phone, 2);
        }

        if (str_starts_with($phone, '0')) {
            return $defaultCountryCode . substr($phone, 1);
        }

        return $defaultCountryCode . $phone;
    }
}
