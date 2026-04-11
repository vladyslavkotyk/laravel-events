<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case PaymentProcessing = 'payment_processing';
    case Paid = 'paid';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Failed = 'failed';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => in_array($next, [self::PaymentProcessing, self::Cancelled]),
            self::PaymentProcessing => in_array($next, [self::Paid, self::Failed]),
            self::Paid => in_array($next, [self::Shipped, self::Refunded]),
            self::Shipped => in_array($next, [self::Delivered, self::Refunded]),
            self::Delivered => in_array($next, [self::Refunded]),
            default => false,
        };
    }
}
