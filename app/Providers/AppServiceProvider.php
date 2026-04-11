<?php

namespace App\Providers;

use App\Events;
use App\Listeners;
use App\Services\PaymentGateway;
use App\Services\PdfGenerator;
use App\Services\ShippingProvider;
use App\Services\StripePaymentGateway;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind service interfaces to concrete implementations.
        // In tests, these can be swapped for fakes.
        $this->app->singleton(PaymentGateway::class, function () {
            return new StripePaymentGateway(
                secretKey: config('services.stripe.secret'),
            );
        });

        $this->app->singleton(ShippingProvider::class, function () {
            // Swap implementation based on config — e.g. EasyPost, ShipStation
            return new \App\Services\EasyPostShippingProvider(
                apiKey: config('services.easypost.key'),
            );
        });

        $this->app->singleton(PdfGenerator::class, function () {
            return new \App\Services\DomPdfGenerator();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * The event→listener map below defines the entire order processing pipeline.
     * Each event may fan out to multiple listeners that run on the queue independently.
     *
     * Flow:
     *   OrderPlaced
     *     └→ ReserveInventory (queued)
     *           └→ dispatches InventoryReserved on success
     *
     *   InventoryReserved
     *     └→ ProcessPayment (queued)
     *           └→ dispatches PaymentProcessed on success
     *           └→ dispatches PaymentFailed on failure
     *
     *   PaymentProcessed
     *     ├→ CreateShipment (queued)
     *     ├→ GenerateInvoice (queued)
     *     └→ SendOrderConfirmation (queued)
     *
     *   ShipmentCreated
     *     └→ SendShipmentNotification (queued)
     *
     *   PaymentFailed
     *     └→ NotifyCustomerOfFailure (queued)
     *
     *   OrderRefunded
     *     └→ ReleaseInventoryOnRefund (queued)
     */
    public function boot(): void
    {
        Event::listen(Events\OrderPlaced::class, Listeners\ReserveInventory::class);

        Event::listen(Events\InventoryReserved::class, Listeners\ProcessPayment::class);

        Event::listen(Events\PaymentProcessed::class, [
            Listeners\CreateShipment::class,
            Listeners\GenerateInvoice::class,
            Listeners\SendOrderConfirmation::class,
        ]);

        Event::listen(Events\ShipmentCreated::class, Listeners\SendShipmentNotification::class);

        Event::listen(Events\PaymentFailed::class, Listeners\NotifyCustomerOfFailure::class);

        Event::listen(Events\OrderRefunded::class, Listeners\ReleaseInventoryOnRefund::class);
    }
}
