<?php

namespace App\Services;

readonly class PaymentResult
{
    public function __construct(
        public string $transactionId,
        public string $status,
    ) {}
}
