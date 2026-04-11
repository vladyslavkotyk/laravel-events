<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Order $order,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Order #{$this->order->id} Confirmed")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your order #{$this->order->id} has been confirmed and payment received.")
            ->line("Total: \${$this->order->total}")
            ->action('View Order', url("/orders/{$this->order->id}"))
            ->line('You will receive a shipping notification once your order ships.');
    }
}
