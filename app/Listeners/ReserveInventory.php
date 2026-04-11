<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Events\InventoryReserved;
use App\Events\OrderPlaced;
use App\Events\PaymentFailed;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReserveInventory implements ShouldQueue
{
    public int $tries = 3;
    public int $backoff = 5;

    public function handle(OrderPlaced $event): void
    {
        $order = $event->order->load('items.product');

        DB::transaction(function () use ($order) {
            foreach ($order->items as $item) {
                $reserved = $item->product->reserveStock($item->quantity);

                if (! $reserved) {
                    // Rollback any stock we already reserved in this transaction
                    $this->rollbackReservedItems($order, $item->id);

                    $order->transitionTo(OrderStatus::Failed);

                    PaymentFailed::dispatch($order, "Insufficient stock for {$item->product->name}");

                    return;
                }
            }

            Log::info('Inventory reserved for order', ['order_id' => $order->id]);

            InventoryReserved::dispatch($order);
        });
    }

    private function rollbackReservedItems($order, int $failedAtItemId): void
    {
        foreach ($order->items as $item) {
            if ($item->id === $failedAtItemId) {
                break;
            }
            $item->product->releaseStock($item->quantity);
        }
    }
}
