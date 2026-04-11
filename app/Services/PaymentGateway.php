<?php

namespace App\Services;

interface PaymentGateway
{
    public function charge(int $amount, string $currency, string $customerId, array $metadata = []): PaymentResult;

    public function refund(string $transactionId, ?int $amount = null): RefundResult;
}
