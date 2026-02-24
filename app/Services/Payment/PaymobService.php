<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;

class PaymobService
{
    private string $integrationId;
    private string $iframeId;
    private string $iframeUrl;

    public function __construct()
    {
        $this->integrationId = env('PAYMOB_INTEGRATION_ID');
        $this->iframeId = env('PAYMOB_IFRAME_ID');
        $this->iframeUrl = env('PAYMOB_IFRAME_URL');
    }

    public function generatePaymentUrl(string $paymentToken): string
    {
        return $this->iframeUrl . $paymentToken;
    }

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