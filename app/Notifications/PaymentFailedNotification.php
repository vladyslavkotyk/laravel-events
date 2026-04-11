<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Order $order,
        private readonly string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Order #{$this->order->id} — Payment Issue")
            ->greeting("Hi {$notifiable->name},")
            ->line("We were unable to process payment for your order #{$this->order->id}.")
            ->line("Reason: {$this->reason}")
            ->action('Update Payment Method', url('/account/payment-methods'))
            ->line('Please update your payment details and try again.');
    }
}
