<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case PaymentFailed = 'payment_failed';
    case Refunded = 'refunded';

    /** @return array<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending Payment',
            self::Paid => 'Paid',
            self::PaymentFailed => 'Payment Failed',
            self::Refunded => 'Refunded',
        };
    }
}

