<?php

namespace App\Notifications;

use App\Models\Withdrawal;
use App\Services\Notification\Channels\SmsWhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WithdrawalStatusUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Withdrawal $withdrawal,
        public string $status,
        public ?string $reason = null
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
        $amountFormatted = '$' . number_format((float)$this->withdrawal->amount, 2) . ' ' . $this->withdrawal->currency;

        if ($this->status === 'approved' || $this->status === 'paid') {
            return (new MailMessage)
                ->subject('💸 Withdrawal Payout Approved - ModerNutrition')
                ->greeting('Hello ' . ($notifiable->first_name ?? 'Distributor') . '!')
                ->line("Your withdrawal payout request **#WD-{$this->withdrawal->withdrawal_number}** for **{$amountFormatted}** has been approved and processed.")
                ->line("**Payment Method:** " . $this->withdrawal->payment_method)
                ->line('The funds have been dispatched to your designated payout account.')
                ->action('View Wallet Statement', "{$appUrl}/wallet")
                ->line('Thank you for partnering with ModerNutrition.');
        }

        if ($this->status === 'rejected') {
            return (new MailMessage)
                ->subject('⚠️ Withdrawal Request Declined - ModerNutrition')
                ->greeting('Hello ' . ($notifiable->first_name ?? 'Distributor') . ',')
                ->line("Your withdrawal request **#WD-{$this->withdrawal->withdrawal_number}** for **{$amountFormatted}** could not be processed.")
                ->line('**Reason:** ' . ($this->reason ?? 'Payment details mismatch or verification required.'))
                ->line('The withdrawn amount has been refunded back to your available wallet balance.')
                ->action('Check Wallet', "{$appUrl}/wallet");
        }

        // Pending submission
        return (new MailMessage)
            ->subject('Withdrawal Request Submitted - ModerNutrition')
            ->greeting('Hello ' . ($notifiable->first_name ?? 'Distributor') . ',')
            ->line("We received your withdrawal request **#WD-{$this->withdrawal->withdrawal_number}** for **{$amountFormatted}**.")
            ->line('Our finance operations team is reviewing your transaction (Maker-Checker compliance).')
            ->action('Track Withdrawal', "{$appUrl}/wallet");
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(mixed $notifiable): array
    {
        $amountFormatted = '$' . number_format((float)$this->withdrawal->amount, 2);

        if ($this->status === 'approved' || $this->status === 'paid') {
            return [
                'type'              => 'withdrawal_approved',
                'title'             => "Withdrawal of {$amountFormatted} Approved!",
                'message'           => "Your withdrawal payout #WD-{$this->withdrawal->withdrawal_number} was approved and disbursed.",
                'withdrawal_number' => $this->withdrawal->withdrawal_number,
                'amount'            => (float)$this->withdrawal->amount,
                'status'            => 'approved',
                'action_url'        => '/wallet',
            ];
        }

        if ($this->status === 'rejected') {
            return [
                'type'              => 'withdrawal_rejected',
                'title'             => "Withdrawal Declined",
                'message'           => "Withdrawal #WD-{$this->withdrawal->withdrawal_number} was declined. Reason: " . ($this->reason ?? 'Verification check failed.'),
                'withdrawal_number' => $this->withdrawal->withdrawal_number,
                'amount'            => (float)$this->withdrawal->amount,
                'status'            => 'rejected',
                'reason'            => $this->reason,
                'action_url'        => '/wallet',
            ];
        }

        return [
            'type'              => 'withdrawal_pending',
            'title'             => "Withdrawal Request Received",
            'message'           => "Your withdrawal of {$amountFormatted} has been submitted for administrative review.",
            'withdrawal_number' => $this->withdrawal->withdrawal_number,
            'amount'            => (float)$this->withdrawal->amount,
            'status'            => 'pending',
            'action_url'        => '/wallet',
        ];
    }

    /**
     * Get the SMS representation of the notification.
     */
    public function toSms(mixed $notifiable): string
    {
        $amountFormatted = '$' . number_format((float)$this->withdrawal->amount, 2);
        if ($this->status === 'approved' || $this->status === 'paid') {
            return "ModerNutrition: Your payout #WD-{$this->withdrawal->withdrawal_number} of {$amountFormatted} has been approved and sent! Check: https://moder-nutrition.vercel.app/wallet";
        }
        if ($this->status === 'rejected') {
            return "ModerNutrition: Withdrawal #WD-{$this->withdrawal->withdrawal_number} was declined ({$this->reason}). Funds refunded to wallet.";
        }
        return "ModerNutrition: Withdrawal of {$amountFormatted} received (#WD-{$this->withdrawal->withdrawal_number}). Reviewing shortly.";
    }

    /**
     * Get the WhatsApp representation of the notification.
     */
    public function toWhatsApp(mixed $notifiable): string
    {
        $amountFormatted = '$' . number_format((float)$this->withdrawal->amount, 2);
        if ($this->status === 'approved' || $this->status === 'paid') {
            return "💸 *ModerNutrition Payout Dispatched!*\n\n*Amount:* {$amountFormatted}\n*Ref:* #WD-{$this->withdrawal->withdrawal_number}\n\nYour withdrawal has been approved and disbursed. Details at https://moder-nutrition.vercel.app/wallet";
        }
        if ($this->status === 'rejected') {
            return "⚠️ *ModerNutrition Payout Notice*\n\nYour withdrawal request was declined.\n*Reason:* {$this->reason}\n\nFunds have been restored to your wallet balance.";
        }
        return "⏳ *ModerNutrition Payout Submitted*\n\nRequest for {$amountFormatted} (#WD-{$this->withdrawal->withdrawal_number}) is in processing.";
    }
}
