<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShipmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Order $order,
        private readonly Shipment $shipment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Order #{$this->order->id} Shipped")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your order #{$this->order->id} has shipped!")
            ->line("Carrier: {$this->shipment->carrier}")
            ->line("Tracking: {$this->shipment->tracking_number}")
            ->action('Track Shipment', url("/orders/{$this->order->id}"));
    }
}
