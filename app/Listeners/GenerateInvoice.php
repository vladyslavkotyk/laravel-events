<?php

namespace App\Listeners;

use App\Events\PaymentProcessed;
use App\Models\Invoice;
use App\Services\PdfGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateInvoice implements ShouldQueue
{
    public int $tries = 3;

    public function __construct(
        private readonly PdfGenerator $pdf,
    ) {}

    public function handle(PaymentProcessed $event): void
    {
        $order = $event->order->load('user', 'items.product', 'payment');

        $invoiceNumber = Invoice::generateNumber();

        $pdfContent = $this->pdf->generate('invoices.template', [
            'invoice_number' => $invoiceNumber,
            'order' => $order,
            'payment' => $event->payment,
            'items' => $order->items,
            'total' => $order->total,
        ]);

        $path = "invoices/{$order->id}/{$invoiceNumber}.pdf";
        Storage::disk('s3')->put($path, $pdfContent);

        Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => $invoiceNumber,
            'amount' => $order->total,
            'issued_at' => now(),
            'pdf_path' => $path,
        ]);

        Log::info('Invoice generated', [
            'order_id' => $order->id,
            'invoice' => $invoiceNumber,
        ]);
    }
}
