<?php

namespace App\Notifications;

use App\Models\Member;
use App\Services\Notification\Channels\SmsWhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewDownlineSponsoredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Member $downline,
        public Member $sponsor,
        public ?string $leg = 'left'
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
        $legName = strtoupper($this->leg ?? 'TEAM');

        return (new MailMessage)
            ->subject('🎉 New Member Enrolled: ' . $this->downline->full_name . ' on ' . $legName . ' Leg')
            ->greeting('Congratulations ' . $this->sponsor->first_name . '!')
            ->line('A new member has enrolled under your sponsorship code.')
            ->line('**Member Name:** ' . $this->downline->full_name)
            ->line('**Member ID:** ' . $this->downline->member_number)
            ->line('**Assigned Binary Leg:** ' . $legName)
            ->action('View Binary Team Tree', "{$appUrl}/team/binary")
            ->line('Keep building your team volume to qualify for Binary & Unilevel bonuses!');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'type'            => 'downline_enrolled',
            'title'           => 'New Team Member Enrolled!',
            'message'         => "{$this->downline->full_name} (#{$this->downline->member_number}) joined your team on the " . strtoupper($this->leg ?? 'left') . " leg.",
            'downline_id'     => $this->downline->id,
            'downline_number' => $this->downline->member_number,
            'leg'             => $this->leg,
            'action_url'      => '/team/binary',
        ];
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms(mixed $notifiable): string
    {
        return "ModerNutrition: {$this->downline->full_name} (#{$this->downline->member_number}) enrolled on your " . strtoupper($this->leg ?? 'left') . " leg. View your team: https://moder-nutrition.vercel.app/team/binary";
    }

    /**
     * Get the WhatsApp representation of the notification.
     */
    public function toWhatsApp(mixed $notifiable): string
    {
        return "🚀 *Great News, {$this->sponsor->first_name}! New Member Enrolled*\n\n*Name:* {$this->downline->full_name}\n*ID:* {$this->downline->member_number}\n*Binary Leg:* " . strtoupper($this->leg ?? 'left') . "\n\nTrack your team volume at https://moder-nutrition.vercel.app/team/binary";
    }
}
