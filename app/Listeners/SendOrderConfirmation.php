<?php

namespace App\Listeners;

use App\Events\PaymentProcessed;
use App\Notifications\OrderConfirmationNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendOrderConfirmation implements ShouldQueue
{
    public function handle(PaymentProcessed $event): void
    {
        $order = $event->order->load('user', 'items.product');

        $order->user->notify(new OrderConfirmationNotification($order));

        Log::info('Order confirmation sent', [
            'order_id' => $order->id,
            'user_email' => $order->user->email,
        ]);
    }
}
