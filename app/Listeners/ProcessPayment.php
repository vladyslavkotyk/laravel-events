<?php

namespace App\Listeners;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\InventoryReserved;
use App\Events\PaymentFailed;
use App\Events\PaymentProcessed;
use App\Models\Payment;
use App\Services\PaymentGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ProcessPayment implements ShouldQueue
{
    public int $tries = 3;
    public array $backoff = [5, 30, 120];

    public function __construct(
        private readonly PaymentGateway $gateway,
    ) {}

    public function handle(InventoryReserved $event): void
    {
        $order = $event->order;
        $order->transitionTo(OrderStatus::PaymentProcessing);

        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => $order->total,
            'currency' => 'USD',
            'status' => PaymentStatus::Processing,
            'gateway' => 'stripe',
        ]);

        try {
            $result = $this->gateway->charge(
                amount: (int) bcmul($order->total, '100'),
                currency: 'usd',
                customerId: $order->user->stripe_customer_id,
                metadata: ['order_id' => $order->id],
            );

            $payment->update([
                'gateway_transaction_id' => $result->transactionId,
                'status' => PaymentStatus::Succeeded,
                'paid_at' => now(),
            ]);

            $order->transitionTo(OrderStatus::Paid);

            Log::info('Payment succeeded', [
                'order_id' => $order->id,
                'transaction_id' => $result->transactionId,
            ]);

            PaymentProcessed::dispatch($order, $payment);
        } catch (\Throwable $e) {
            $payment->update(['status' => PaymentStatus::Failed]);

            Log::error('Payment failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            // Release the reserved inventory
            foreach ($order->items()->with('product')->get() as $item) {
                $item->product->releaseStock($item->quantity);
            }

            $order->transitionTo(OrderStatus::Failed);

            PaymentFailed::dispatch($order, $e->getMessage());
        }
    }

    public function failed(InventoryReserved $event, \Throwable $exception): void
    {
        Log::critical('Payment processing exhausted all retries', [
            'order_id' => $event->order->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
