<?php

declare(strict_types=1);

namespace App\Contracts;

interface PaymentGatewayInterface
{
    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function charge(int $amountInCents, array $metadata = []): array;

    /**
     * @return array<string, mixed>
     */
    public function refund(string $transactionId, int $amountInCents): array;

    /**
     * Process a payment for the given order.
     *
     * @param array $orderDetails
     * @return array
     */
    public function processPayment(array $orderDetails): array;
}



