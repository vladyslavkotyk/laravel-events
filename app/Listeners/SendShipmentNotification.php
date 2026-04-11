<?php

namespace App\Listeners;

use App\Events\ShipmentCreated;
use App\Notifications\ShipmentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendShipmentNotification implements ShouldQueue
{
    public function handle(ShipmentCreated $event): void
    {
        $event->order->user->notify(
            new ShipmentNotification($event->order, $event->shipment)
        );
    }
}
