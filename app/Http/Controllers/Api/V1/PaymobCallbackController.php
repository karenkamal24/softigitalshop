<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymobCallbackController extends Controller
{
    /**
     * Transaction Webhook — Paymob calls this endpoint with every transaction result.
     * Configure this URL in your Paymob dashboard under:
     *   Settings → Payment Integrations → Transaction Processed Callback
     *
     * URL: POST /api/v1/paymob/webhook
     */
    public function webhook(Request $request): JsonResponse
    {
        $data = $request->all();

        Log::info('Paymob webhook received', ['payload' => $data]);

        // ── 1. Verify HMAC signature ──────────────────────────────────────────
        if (! $this->verifyHmac($data)) {
            Log::warning('Paymob webhook: invalid HMAC signature', ['payload' => $data]);

            return response()->json(['status' => 'invalid_signature'], 401);
        }

        // ── 2. Locate the order ───────────────────────────────────────────────
        $paymobOrderId = (string) ($data['obj']['order']['id'] ?? ($data['order']['id'] ?? ''));

        if (empty($paymobOrderId)) {
            return response()->json(['status' => 'missing_order_id'], 400);
        }

        /** @var Order|null $order */
        $order = Order::withoutGlobalScope('active')
            ->where('paymob_order_id', $paymobOrderId)
            ->first();

        if (! $order) {
            Log::warning('Paymob webhook: order not found', ['paymob_order_id' => $paymobOrderId]);

            return response()->json(['status' => 'order_not_found'], 404);
        }

        // ── 3. Parse transaction result ───────────────────────────────────────
        $obj     = $data['obj'] ?? $data;
        $success = filter_var($obj['success'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $pending = filter_var($obj['pending'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($success) {
            $order->update([
                'payment_status'  => 'paid',
                'status'          => 'confirmed',
                'transaction_id'  => (string) ($obj['id'] ?? $order->transaction_id),
            ]);

            Log::info('Paymob webhook: order marked as paid', ['order_id' => $order->id]);
        } elseif ($pending) {
            $order->update(['payment_status' => 'pending_payment', 'status' => 'pending']);
        } else {
            $order->update(['payment_status' => 'payment_failed', 'status' => 'pending']);

            Log::info('Paymob webhook: payment failed', ['order_id' => $order->id]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Response Callback — Paymob redirects the user back here after the iframe payment.
     * Configure this URL in your Paymob dashboard under:
     *   Settings → Payment Integrations → Redirect URL
     *
     * URL: GET /api/v1/paymob/response
     */
    public function response(Request $request): JsonResponse
    {
        $success       = filter_var($request->query('success'), FILTER_VALIDATE_BOOLEAN);
        $paymobOrderId = (string) $request->query('order', '');

        if (! $success) {
            return response()->json([
                'status'  => 'failed',
                'message' => 'Payment was not successful. Please try again.',
            ], 400);
        }

        // Optionally find the order and return details
        $order = Order::withoutGlobalScope('active')
            ->where('paymob_order_id', $paymobOrderId)
            ->first();

        return response()->json([
            'status'       => 'success',
            'message'      => 'Payment completed. Your order is being processed.',
            'order_number' => $order?->order_number,
        ]);
    }

    // -------------------------------------------------------------------------
    // HMAC verification
    // -------------------------------------------------------------------------

    /**
     * Verify Paymob HMAC-SHA512 signature.
     *
     * Paymob sends the webhook as:  { "obj": { ...transaction fields... }, "type": "TRANSACTION" }
     * The HMAC is calculated by concatenating specific transaction fields in alphabetical order
     * and hashing with HMAC-SHA512 using your HMAC secret.
     *
     * @param array<string, mixed> $data
     */
    private function verifyHmac(array $data): bool
    {
        $hmacSecret = config('payment.gateways.paymob.hmac_secret');

        if (empty($hmacSecret)) {
            // Skip verification if no secret configured (development only)
            return true;
        }

        $receivedHmac = (string) ($data['hmac'] ?? '');

        // Paymob HMAC fields (must be in this exact order)
        $fields = [
            'amount_cents',
            'created_at',
            'currency',
            'error_occured',
            'has_parent_transaction',
            'id',
            'integration_id',
            'is_3d_secure',
            'is_auth',
            'is_capture',
            'is_refunded',
            'is_standalone_payment',
            'is_voided',
            'order.id',
            'owner',
            'pending',
            'source_data.pan',
            'source_data.sub_type',
            'source_data.type',
            'success',
        ];

        // Paymob wraps the transaction data inside "obj"
        $obj = $data['obj'] ?? $data;

        $concatenated = '';
        foreach ($fields as $field) {
            if (str_contains($field, '.')) {
                [$parent, $child] = explode('.', $field, 2);
                $value = $obj[$parent][$child] ?? '';
            } else {
                $value = $obj[$field] ?? '';
            }

            // Booleans must be serialised as "true"/"false" strings
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $concatenated .= (string) $value;
        }

        $calculatedHmac = hash_hmac('sha512', $concatenated, $hmacSecret);

        return hash_equals($calculatedHmac, $receivedHmac);
    }
}

