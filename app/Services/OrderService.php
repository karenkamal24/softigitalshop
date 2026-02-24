<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Jobs\NotifyFulfillmentServiceJob;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function __construct(
        private readonly PaymentGatewayInterface $paymentGateway,
    ) {}

    /**
     * @param array<int, array{product_id: int, quantity: int}> $items
     * @param string|null $address
     * @return array{order: Order, payment_url: string|null}
     *
     * @throws ValidationException
     */
    public function placeOrder(User $user, array $items, ?string $address = null): array
    {
        return DB::transaction(function () use ($user, $items, $address): array {
            /** @var array<int, array{product_id: int, quantity: int, unit_price_cents: int}> $orderItems */
            $orderItems = [];
            $totalAmountCents = 0;
            $totalQuantity = 0;

            foreach ($items as $item) {
                $product = Product::where('is_active', true)
                    ->lockForUpdate()
                    ->find($item['product_id']);

                if (! $product instanceof Product) {
                    throw ValidationException::withMessages([
                        'items' => ["Product with ID {$item['product_id']} is not available."],
                    ]);
                }

                if ($product->stock < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'items' => ["Insufficient stock for product '{$product->name}'."],
                    ]);
                }

                $lineTotal = $product->price_in_cents * $item['quantity'];
                $totalAmountCents += $lineTotal;
                $totalQuantity += $item['quantity'];

                $orderItems[] = [
                    'product_id'      => $product->id,
                    'quantity'        => $item['quantity'],
                    'unit_price_cents' => $product->price_in_cents,
                ];

                $product->decrement('stock', $item['quantity']);
            }

            $paymentDetails = [
                'amount'      => $totalAmountCents,
                'user_id'     => $user->id,
                'user'        => $user,          // pass full user for real billing data
                'order_items' => $orderItems,
            ];

            $paymentResponse = $this->paymentGateway->processPayment($paymentDetails);

            if ($paymentResponse['status'] !== 'success') {
                throw new \Exception('Payment failed: ' . ($paymentResponse['message'] ?? 'Unknown error'));
            }

            $initialStatus = $paymentResponse['initial_status'] ?? 'confirmed';
            $paymentStatus = $initialStatus === 'confirmed' ? 'paid' : 'pending_payment';
            $orderStatus = $initialStatus === 'confirmed' ? 'confirmed' : 'pending';

            $orderAddress = $address ?? $user->address;
            if (empty($orderAddress)) {
                throw ValidationException::withMessages([
                    'address' => ['Please provide a delivery address or add one to your profile.'],
                ]);
            }

            $order = Order::create([
                'user_id'           => $user->id,
                'order_number'      => 'ORD-' . strtoupper(Str::random(10)),
                'total_amount_cents' => $totalAmountCents,
                'total_quantity'    => $totalQuantity,
                'status'            => $orderStatus,
                'payment_status'    => $paymentStatus,
                'address'           => $orderAddress,
                'paymob_order_id'   => $paymentResponse['paymob_order_id'] ?? null,
                'transaction_id'    => $paymentResponse['transaction_id'] ?? null,
            ]);

            $order->items()->createMany($orderItems);

            NotifyFulfillmentServiceJob::dispatch($order->load(['items', 'user']));

            return [
                'order'       => $order->load('items'),
                'payment_url' => $paymentResponse['payment_url'] ?? null,
            ];
        });
    }
}
