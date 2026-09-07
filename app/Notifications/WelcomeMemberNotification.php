<?php

namespace App\Notifications;

use App\Models\Member;
use App\Services\Notification\Channels\SmsWhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeMemberNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Member $member
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

        return (new MailMessage)
            ->subject('Welcome to ModerNutrition - Member ID #' . $this->member->member_number)
            ->greeting('Hello ' . $this->member->first_name . '!')
            ->line('Welcome to the ModerNutrition wellness community and distribution network.')
            ->line('Your unique Member ID is: **' . $this->member->member_number . '**')
            ->line('You can now log in to your Member Portal, browse discounted nutrition products, and track your team binary volume.')
            ->action('Access Member Portal', "{$appUrl}/login")
            ->line('If you have any questions, our support team is ready to assist you.');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(mixed $notifiable): array
    {
        return [
            'type'          => 'welcome',
            'title'         => 'Welcome to ModerNutrition!',
            'message'       => "Welcome {$this->member->first_name}! Your Member ID is {$this->member->member_number}. Start exploring your dashboard and products.",
            'member_number' => $this->member->member_number,
            'action_url'    => '/dashboard',
        ];
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(mixed $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms(mixed $notifiable): string
    {
        return "Welcome to ModerNutrition {$this->member->first_name}! Your Member ID is {$this->member->member_number}. Log in at https://moder-nutrition.vercel.app to start.";
    }

    /**
     * Get the WhatsApp representation of the notification.
     */
    public function toWhatsApp(mixed $notifiable): string
    {
        return "👋 *Welcome to ModerNutrition, {$this->member->first_name}!*\n\nYour Member ID is *{$this->member->member_number}*.\n\nAccess your Member Portal at https://moder-nutrition.vercel.app to view your dashboard, place product orders, and grow your binary team.";
    }
}
