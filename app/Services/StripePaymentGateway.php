<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class StripePaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $secretKey,
    ) {}

    public function charge(int $amount, string $currency, string $customerId, array $metadata = []): PaymentResult
    {
        $response = Http::withToken($this->secretKey)
            ->post('https://api.stripe.com/v1/payment_intents', [
                'amount' => $amount,
                'currency' => $currency,
                'customer' => $customerId,
                'metadata' => $metadata,
                'confirm' => true,
            ]);

        $response->throw();

        return new PaymentResult(
            transactionId: $response->json('id'),
            status: $response->json('status'),
        );
    }

    public function refund(string $transactionId, ?int $amount = null): RefundResult
    {
        $payload = ['payment_intent' => $transactionId];

        if ($amount !== null) {
            $payload['amount'] = $amount;
        }

        $response = Http::withToken($this->secretKey)
            ->post('https://api.stripe.com/v1/refunds', $payload);

        $response->throw();

        return new RefundResult(
            refundId: $response->json('id'),
            status: $response->json('status'),
        );
    }
}
