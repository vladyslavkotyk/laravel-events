<?php

namespace App\Services;

readonly class TrackingInfo
{
    public function __construct(
        public string $status,
        public ?string $estimatedDelivery,
        public array $history,
    ) {}
}
