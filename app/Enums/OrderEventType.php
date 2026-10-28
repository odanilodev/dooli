<?php

namespace App\Enums;

enum OrderEventType: string
{
    case OrderCreated = 'order.created';
    case OrderCanceled = 'order.canceled';
    case OrderRefunded = 'order.refunded';
    case PaymentStarted = 'payment.started';
    case PaymentApproved = 'payment.approved';
    case PaymentFailed = 'payment.failed';
    case PaymentRefunded = 'payment.refunded';
    case PaymentBlocked = 'payment.blocked';
    case AccessGranted = 'access.granted';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
