<?php

namespace App\Services;

readonly class ShippingLabel
{
    public function __construct(
        public string $trackingNumber,
        public string $carrier,
        public string $labelUrl,
    ) {}
}
