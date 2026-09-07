<?php

namespace App\Notifications;

use App\Models\Member;
use App\Services\Notification\Channels\SmsWhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KycStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Member $member,
        public string $kycStatus,
        public ?string $rejectionReason = null
    ) {}

    /**
     * Get the notification's delivery channels.
     */
    public function via(mixed $notifiable): array
    {
        return ['mail', 'database', SmsWhatsAppChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(mixed $notifiable): MailMessage
    {
        $appUrl = env('FRONTEND_URL', 'https://moder-nutrition.vercel.app');

        if ($this->kycStatus === 'verified') {
            return (new MailMessage)
                ->subject('✅ KYC Verification Approved - ModerNutrition')
                ->greeting('Hello ' . $this->member->first_name . '!')
                ->line('Congratulations! Your identity and KYC documents have been successfully verified and approved.')
                ->line('Your distributor account is now fully unlocked for wallet withdrawals and bonus payouts.')
                ->action('Go to Dashboard', "{$appUrl}/dashboard")
                ->line('Thank you for verifying your identity with ModerNutrition.');
        }

        return (new MailMessage)
            ->subject('⚠️ KYC Verification Action Required - ModerNutrition')
            ->greeting('Hello ' . $this->member->first_name . ',')
            ->line('Our compliance team reviewed your submitted KYC documents, but we were unable to approve them.')
            ->line('**Reason:** ' . ($this->rejectionReason ?? 'Document unclear or invalid identification provided.'))
            ->line('Please re-upload a clear copy of your National ID / Passport and Proof of Address.')
            ->action('Re-submit KYC Documents', "{$appUrl}/kyc")
            ->line('If you need assistance, please contact our support team.');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(mixed $notifiable): array
    {
        if ($this->kycStatus === 'verified') {
            return [
                'type'       => 'kyc_verified',
                'title'      => 'KYC Verification Approved!',
                'message'    => 'Your identity verification was approved. All account withdrawal features are now unlocked.',
                'kyc_status' => 'verified',
                'action_url' => '/dashboard',
            ];
        }

        return [
            'type'             => 'kyc_rejected',
            'title'            => 'KYC Verification Incomplete',
            'message'          => 'Your KYC was rejected: ' . ($this->rejectionReason ?? 'Please re-upload clear identity documents.'),
            'kyc_status'       => 'rejected',
            'rejection_reason' => $this->rejectionReason,
            'action_url'       => '/kyc',
        ];
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms(mixed $notifiable): string
    {
        if ($this->kycStatus === 'verified') {
            return "ModerNutrition: Great news! Your KYC has been approved and account verified. Log in at https://moder-nutrition.vercel.app";
        }
        return "ModerNutrition: Your KYC could not be verified. Reason: {$this->rejectionReason}. Please re-submit at https://moder-nutrition.vercel.app/kyc";
    }

    /**
     * Get the WhatsApp representation of the notification.
     */
    public function toWhatsApp(mixed $notifiable): string
    {
        if ($this->kycStatus === 'verified') {
            return "✅ *ModerNutrition KYC Verification Approved!*\n\nHello {$this->member->first_name}, your identity verification has been approved. Your payout privileges are active.\n\nDashboard: https://moder-nutrition.vercel.app/dashboard";
        }
        return "⚠️ *ModerNutrition KYC Verification Notice*\n\nHello {$this->member->first_name}, your KYC documents were not approved.\n\n*Reason:* {$this->rejectionReason}\n\nPlease re-upload documents at https://moder-nutrition.vercel.app/kyc";
    }
}
