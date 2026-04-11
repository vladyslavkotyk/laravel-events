<?php

namespace App\Services;

readonly class RefundResult
{
    public function __construct(
        public string $refundId,
        public string $status,
    ) {}
}
