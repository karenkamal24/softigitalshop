<?php

declare(strict_types=1);

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;

class PaymobService
{
    private string $integrationId;
    private string $iframeUrl;

    public function __construct()
    {
        $this->integrationId = (string) config('payment.gateways.paymob.integration_id', '');
        $this->iframeUrl = (string) config('payment.gateways.paymob.iframe_url', '');
    }

    public function generatePaymentUrl(string $paymentToken): string
    {
        return $this->iframeUrl . $paymentToken;
    }

    /** @param array<string, mixed> $orderDetails */
    public function createPaymentToken(array $orderDetails): string
    {
        // Mock API call to Paymob to generate a payment token
        $response = Http::post('https://accept.paymob.com/api/acceptance/payment_keys', [
            'integration_id' => $this->integrationId,
            'amount_cents' => $orderDetails['amount'],
            'currency' => 'EGP',
            'order_id' => $orderDetails['order_id'],
        ]);

        return $response->json('token');
    }
}