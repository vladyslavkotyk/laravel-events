<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Events\OrderPlaced;
use App\Events\OrderRefunded;
use App\Models\Order;
use App\Models\Product;
use App\Services\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()
            ->orders()
            ->with('items.product')
            ->latest()
            ->paginate(20);

        return response()->json($orders);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $order->load([
            'items.product',
            'payment',
            'shipment',
            'invoice',
            'events',
        ]);

        return response()->json($order);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        return DB::transaction(function () use ($request, $validated) {
            // Lock the products to calculate a consistent total
            $productIds = collect($validated['items'])->pluck('product_id');
            $products = Product::whereIn('id', $productIds)->lockForUpdate()->get()->keyBy('id');

            $total = '0.00';
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $product = $products->get($item['product_id']);

                if (! $product || $product->stock < $item['quantity']) {
                    return response()->json([
                        'error' => "Insufficient stock for {$product?->name ?? 'unknown product'}",
                    ], 422);
                }

                $lineTotal = bcmul($product->price, (string) $item['quantity'], 2);
                $total = bcadd($total, $lineTotal, 2);

                $itemsData[] = [
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->price,
                ];
            }

            $order = Order::create([
                'user_id' => $request->user()->id,
                'status' => OrderStatus::Pending,
                'total' => $total,
                'notes' => $validated['notes'] ?? null,
            ]);

            $order->items()->createMany($itemsData);

            $order->events()->create([
                'status' => OrderStatus::Pending,
                'metadata' => ['ip' => $request->ip()],
            ]);

            // This kicks off the entire event chain:
            // OrderPlaced → ReserveInventory → InventoryReserved → ProcessPayment
            //   → PaymentProcessed → [CreateShipment, GenerateInvoice, SendOrderConfirmation]
            OrderPlaced::dispatch($order);

            return response()->json([
                'order_id' => $order->id,
                'status' => $order->status,
                'total' => $order->total,
            ], 201);
        });
    }

    public function cancel(Request $request, Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        if ($order->status !== OrderStatus::Pending) {
            return response()->json([
                'error' => 'Only pending orders can be cancelled',
            ], 422);
        }

        $order->transitionTo(OrderStatus::Cancelled);

        return response()->json(['status' => 'cancelled']);
    }

    public function refund(Request $request, Order $order, PaymentGateway $gateway): JsonResponse
    {
        $this->authorize('refund', $order);

        if (! in_array($order->status, [OrderStatus::Paid, OrderStatus::Shipped, OrderStatus::Delivered])) {
            return response()->json([
                'error' => 'Order is not eligible for refund',
            ], 422);
        }

        $payment = $order->payment;

        if (! $payment?->gateway_transaction_id) {
            return response()->json(['error' => 'No payment found'], 422);
        }

        $result = $gateway->refund($payment->gateway_transaction_id);

        OrderRefunded::dispatch($order, $result->refundId);

        return response()->json([
            'status' => 'refunded',
            'refund_id' => $result->refundId,
        ]);
    }
}
