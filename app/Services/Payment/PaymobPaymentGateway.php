<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Contracts\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymobPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $integrationId,
        private readonly string $iframeId,
        private readonly string $merchantId,
        private readonly string $baseUrl,
        /** @phpstan-ignore property.onlyWritten */
        private readonly ?string $hmacSecret = null,
    ) {}

    /**
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    public function charge(int $amountInCents, array $metadata = []): array
    {
        return $this->processPayment([
            'amount'      => $amountInCents,
            'user_id'     => $metadata['user_id'] ?? null,
            'order_items' => $metadata['order_items'] ?? [],
            'user'        => $metadata['user'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function refund(string $transactionId, int $amountInCents): array
    {
        $authToken = $this->getAuthToken();
        if (! $authToken) {
            return [
                'status'  => 'failed',
                'message' => 'Failed to authenticate with Paymob',
            ];
        }

        $response = Http::post("{$this->baseUrl}/acceptance/void_refund/refund", [
            'auth_token'     => $authToken,
            'transaction_id' => $transactionId,
            'amount_cents'   => $amountInCents,
        ]);

        $data = $response->json();

        return [
            'status'         => ($data['refunded_amount_cents'] ?? 0) >= $amountInCents ? 'refunded' : 'failed',
            'refund_id'      => $data['id'] ?? null,
            'transaction_id' => $transactionId,
            'message'        => $data['message'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $orderDetails
     * @return array<string, mixed>
     */
    public function processPayment(array $orderDetails): array
    {
        $amount = (int) ($orderDetails['amount'] ?? 0);
        if ($amount <= 0) {
            return [
                'status'  => 'failed',
                'message' => 'Invalid amount',
            ];
        }

        $authToken = $this->getAuthToken();
        if (! $authToken) {
            return [
                'status'  => 'failed',
                'message' => 'Failed to authenticate with Paymob',
            ];
        }

        $paymobOrderId = $this->createPaymobOrder($authToken, $amount);
        if (! $paymobOrderId) {
            Log::error('Paymob: failed to create order', [
                'amount_cents' => $amount,
                'merchant_id'  => $this->merchantId ?: '(not set)',
            ]);

            return [
                'status'  => 'failed',
                'message' => 'Failed to create order in Paymob — check laravel.log for details',
            ];
        }

        // Build billing data from real user data when available
        $billingData = $this->buildBillingData($orderDetails['user'] ?? null);

        $paymentToken = $this->getPaymentKey($authToken, $paymobOrderId, $amount, $billingData);
        if (! $paymentToken) {
            return [
                'status'  => 'failed',
                'message' => 'Failed to get payment key from Paymob',
            ];
        }

        $paymentUrl = "https://accept.paymob.com/api/acceptance/iframes/{$this->iframeId}?payment_token={$paymentToken}";

        return [
            'status'           => 'success',
            'initial_status'   => 'pending_payment',   // order waits for webhook confirmation
            'transaction_id'   => 'paymob_' . $paymobOrderId . '_' . Str::random(8),
            'payment_token'    => $paymentToken,
            'payment_url'      => $paymentUrl,
            'paymob_order_id'  => (string) $paymobOrderId,
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function getAuthToken(): ?string
    {
        $response = Http::post("{$this->baseUrl}/auth/tokens", [
            'api_key' => $this->apiKey,
        ]);

        Log::debug('Paymob auth response', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        $token = $response->json('token');

        return is_string($token) ? $token : null;
    }

    private function createPaymobOrder(string $authToken, int $amountCents): ?int
    {
        $payload = [
            'auth_token'      => $authToken,
            'delivery_needed' => false,
            'amount_cents'    => $amountCents,
            'currency'        => 'EGP',
            'items'           => [],
        ];

        // merchant_id is optional — only include it when explicitly configured
        if (! empty($this->merchantId)) {
            $payload['merchant_id'] = $this->merchantId;
        }

        $response = Http::post("{$this->baseUrl}/ecommerce/orders", $payload);

        Log::debug('Paymob create order response', [
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        $id = $response->json('id');

        return is_int($id) ? $id : null;
    }

    /**
     * @param array<string, string> $billingData
     */
    private function getPaymentKey(
        string $authToken,
        int $paymobOrderId,
        int $amountCents,
        array $billingData,
    ): ?string {
        $response = Http::post("{$this->baseUrl}/acceptance/payment_keys", [
            'auth_token'   => $authToken,
            'amount_cents' => $amountCents,
            'expiration'   => 3600,
            'order_id'     => $paymobOrderId,
            'billing_data' => $billingData,
            'currency'     => 'EGP',
            'integration_id' => (int) $this->integrationId,
        ]);

        $token = $response->json('token');

        return is_string($token) ? $token : null;
    }

    /**
     * Build Paymob billing_data from the authenticated user model.
     *
     * @param \App\Models\User|null $user
     * @return array<string, string>
     */
    private function buildBillingData(?object $user): array
    {
        $firstName = 'Customer';
        $lastName  = 'User';
        $email     = 'customer@example.com';
        $phone     = '01000000000';

        if ($user !== null) {
            $nameParts = explode(' ', trim((string) ($user->name ?? '')), 2);
            $firstName = $nameParts[0] ?: 'Customer';
            $lastName  = $nameParts[1] ?? 'User';
            $email     = (string) ($user->email ?? $email);
            $phone     = (string) ($user->phone ?? $phone);
        }

        return [
            'apartment'       => 'NA',
            'email'           => $email,
            'floor'           => 'NA',
            'first_name'      => $firstName,
            'street'          => 'NA',
            'building'        => 'NA',
            'phone_number'    => $phone,
            'shipping_method' => 'NA',
            'postal_code'     => 'NA',
            'city'            => 'Cairo',
            'country'         => 'EGY',
            'last_name'       => $lastName,
            'state'           => 'NA',
        ];
    }
}
