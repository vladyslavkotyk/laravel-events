<?php

namespace App\Listeners;

use App\Enums\ShipmentStatus;
use App\Events\PaymentProcessed;
use App\Events\ShipmentCreated;
use App\Models\Shipment;
use App\Services\ShippingProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class CreateShipment implements ShouldQueue
{
    public int $tries = 3;
    public array $backoff = [10, 60, 300];

    public function __construct(
        private readonly ShippingProvider $shipping,
    ) {}

    public function handle(PaymentProcessed $event): void
    {
        $order = $event->order->load('user', 'items.product');

        $label = $this->shipping->createLabel(
            recipientName: $order->user->name,
            address: $order->user->shipping_address,
            items: $order->items->map(fn ($item) => [
                'sku' => $item->product->sku,
                'quantity' => $item->quantity,
                'weight_oz' => $item->product->weight_oz ?? 16,
            ])->toArray(),
        );

        $shipment = Shipment::create([
            'order_id' => $order->id,
            'tracking_number' => $label->trackingNumber,
            'carrier' => $label->carrier,
            'status' => ShipmentStatus::LabelCreated,
        ]);

        Log::info('Shipment created', [
            'order_id' => $order->id,
            'tracking' => $label->trackingNumber,
        ]);

        ShipmentCreated::dispatch($order, $shipment);
    }
}
