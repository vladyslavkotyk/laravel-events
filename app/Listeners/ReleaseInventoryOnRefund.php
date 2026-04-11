<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\OrderRefunded;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ReleaseInventoryOnRefund implements ShouldQueue
{
    public function handle(OrderRefunded $event): void
    {
        $order = $event->order->load('items.product');

        foreach ($order->items as $item) {
            $item->product->releaseStock($item->quantity);
        }

        $order->transitionTo(OrderStatus::Refunded);

        Log::info('Inventory released for refunded order', [
            'order_id' => $order->id,
        ]);
    }
}
