<?php

namespace App\Services;

interface ShippingProvider
{
    public function createLabel(string $recipientName, string $address, array $items): ShippingLabel;

    public function getTracking(string $trackingNumber): TrackingInfo;
}
