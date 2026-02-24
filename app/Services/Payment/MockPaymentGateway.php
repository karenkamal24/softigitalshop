<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Str;

class MockPaymentGateway implements PaymentGatewayInterface
{
    public function charge(int $amountInCents, array $metadata = []): array
    {
        return [
            'transaction_id' => 'txn_' . Str::random(20),
            'amount_in_cents' => $amountInCents,
            'status' => 'succeeded',
            'metadata' => $metadata,
        ];
    }

    public function refund(string $transactionId, int $amountInCents): array
    {
        return [
            'refund_id' => 'ref_' . Str::random(20),
            'transaction_id' => $transactionId,
            'amount_in_cents' => $amountInCents,
            'status' => 'refunded',
        ];
    }

    public function processPayment(array $orderDetails): array
    {
        $amount = $orderDetails['amount'] ?? 0;
        $result = $this->charge($amount, $orderDetails);
        $result['status']         = 'success';
        $result['initial_status'] = 'confirmed';

        return $result;
    }
}



