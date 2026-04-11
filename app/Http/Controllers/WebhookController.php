<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Payment;
use App\Models\Shipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle Stripe webhook events.
     *
     * Stripe signs every webhook with a signature header so we can verify
     * authenticity. We handle the events idempotently — if the same event
     * arrives twice, the second is a no-op.
     */
    public function stripe(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                config('services.stripe.webhook_secret'),
            );
        } catch (\Exception $e) {
            Log::warning('Stripe webhook signature verification failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 403);
        }

        Log::info('Stripe webhook received', [
            'type' => $event->type,
            'id' => $event->id,
        ]);

        match ($event->type) {
            'payment_intent.succeeded' => $this->handlePaymentSucceeded($event->data->object),
            'payment_intent.payment_failed' => $this->handlePaymentFailed($event->data->object),
            'charge.refunded' => $this->handleChargeRefunded($event->data->object),
            default => Log::info("Unhandled Stripe event: {$event->type}"),
        };

        return response()->json(['received' => true]);
    }

    /**
     * Handle shipping carrier webhook events.
     *
     * The carrier sends tracking updates as packages move through their network.
     * We verify the request via a shared HMAC secret.
     */
    public function shipping(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tracking_number' => 'required|string',
            'status' => 'required|string',
            'timestamp' => 'required|date',
        ]);

        $expectedSignature = hash_hmac(
            'sha256',
            $request->getContent(),
            config('services.shipping.webhook_secret'),
        );

        if (! hash_equals($expectedSignature, $request->header('X-Webhook-Signature', ''))) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        $shipment = Shipment::where('tracking_number', $validated['tracking_number'])->first();

        if (! $shipment) {
            return response()->json(['error' => 'Unknown tracking number'], 404);
        }

        $newStatus = match ($validated['status']) {
            'picked_up', 'in_transit' => ShipmentStatus::InTransit,
            'delivered' => ShipmentStatus::Delivered,
            default => null,
        };

        if ($newStatus && $newStatus !== $shipment->status) {
            $shipment->update([
                'status' => $newStatus,
                'delivered_at' => $newStatus === ShipmentStatus::Delivered ? now() : null,
            ]);

            if ($newStatus === ShipmentStatus::Delivered) {
                $shipment->order->transitionTo(OrderStatus::Delivered);
            }

            Log::info('Shipment status updated via webhook', [
                'tracking' => $shipment->tracking_number,
                'status' => $newStatus->value,
            ]);
        }

        return response()->json(['received' => true]);
    }

    private function handlePaymentSucceeded(object $paymentIntent): void
    {
        $payment = Payment::where('gateway_transaction_id', $paymentIntent->id)->first();

        if ($payment) {
            Log::info('Payment confirmed via webhook', ['payment_id' => $payment->id]);
        }
    }

    private function handlePaymentFailed(object $paymentIntent): void
    {
        Log::warning('Payment failed via webhook', [
            'payment_intent' => $paymentIntent->id,
            'error' => $paymentIntent->last_payment_error?->message,
        ]);
    }

    private function handleChargeRefunded(object $charge): void
    {
        Log::info('Charge refunded via webhook', ['charge_id' => $charge->id]);
    }
}
