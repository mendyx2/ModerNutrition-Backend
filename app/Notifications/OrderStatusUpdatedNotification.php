<?php

namespace App\Notifications;

use App\Models\Order;
use App\Services\Notification\Channels\SmsWhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
        public string $status
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
        $statusLabel = strtoupper($this->status);
        $totalFormatted = '$' . number_format($this->order->total_cents / 100, 2);

        $mail = (new MailMessage)
            ->subject("Order Update: #{$this->order->order_number} is now {$statusLabel}")
            ->greeting('Hello ' . ($notifiable->first_name ?? 'Valued Customer') . ',')
            ->line("Your Order **#{$this->order->order_number}** has been updated to **{$statusLabel}**.")
            ->line("**Order Total:** {$totalFormatted} | **PV Generated:** " . number_format($this->order->total_pv, 2) . " PV");

        if ($this->status === 'shipped') {
            if (!empty($this->order->tracking_number)) {
                $mail->line("**Tracking Number:** " . $this->order->tracking_number);
            }
            if (!empty($this->order->shipping_address)) {
                $mail->line("**Destination Address:** " . $this->order->shipping_address);
            }
        }

        return $mail
            ->action('View Order Details', "{$appUrl}/orders/{$this->order->id}")
            ->line('Thank you for choosing ModerNutrition.');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(mixed $notifiable): array
    {
        $statusLabel = ucfirst($this->status);
        return [
            'type'            => 'order_status_updated',
            'title'           => "Order #{$this->order->order_number} is {$statusLabel}",
            'message'         => "Your order of $" . number_format($this->order->total_cents / 100, 2) . " has been updated to {$statusLabel}.",
            'order_id'        => $this->order->id,
            'order_number'    => $this->order->order_number,
            'status'          => $this->status,
            'tracking_number' => $this->order->tracking_number,
            'action_url'      => "/orders/{$this->order->id}",
        ];
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms(mixed $notifiable): string
    {
        $statusLabel = strtoupper($this->status);
        $tracking = !empty($this->order->tracking_number) ? " Tracking: {$this->order->tracking_number}." : "";
        return "ModerNutrition: Order #{$this->order->order_number} is {$statusLabel}.{$tracking} Track at: https://moder-nutrition.vercel.app/orders/{$this->order->id}";
    }

    /**
     * Get the WhatsApp representation of the notification.
     */
    public function toWhatsApp(mixed $notifiable): string
    {
        $statusLabel = strtoupper($this->status);
        $totalFormatted = '$' . number_format($this->order->total_cents / 100, 2);
        $tracking = !empty($this->order->tracking_number) ? "\n*Tracking Number:* {$this->order->tracking_number}" : "";

        return "📦 *ModerNutrition Order Update*\n\n*Order:* #{$this->order->order_number}\n*Status:* {$statusLabel}\n*Total:* {$totalFormatted}{$tracking}\n\nView details: https://moder-nutrition.vercel.app/orders/{$this->order->id}";
    }
}
