<?php

namespace App\Listeners;

use App\Events\PaymentFailed;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class NotifyCustomerOfFailure implements ShouldQueue
{
    public function handle(PaymentFailed $event): void
    {
        $order = $event->order->load('user');

        $order->user->notify(
            new PaymentFailedNotification($order, $event->reason)
        );

        Log::warning('Customer notified of payment failure', [
            'order_id' => $order->id,
            'reason' => $event->reason,
        ]);
    }
}
