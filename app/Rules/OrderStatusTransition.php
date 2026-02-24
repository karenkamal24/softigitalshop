<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\OrderStatus;
use App\Models\Order;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class OrderStatusTransition implements ValidationRule
{
    public function __construct(
        private readonly Order $order
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $current = OrderStatus::tryFrom($this->order->status);
        $target = OrderStatus::tryFrom((string) $value);

        if ($current === null || $target === null) {
            $fail('Invalid order status.');
            return;
        }

        if (! OrderStatus::canTransitionTo($current, $target)) {
            $fail("Cannot transition from '{$this->order->status}' to '{$value}'.");
        }
    }
}

