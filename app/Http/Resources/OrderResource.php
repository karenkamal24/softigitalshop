<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Order */
class OrderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $orderStatusEnum = OrderStatus::tryFrom($this->status);
        $paymentStatusEnum = PaymentStatus::tryFrom($this->payment_status ?? 'pending_payment');

        return [
            'id'                 => $this->id,
            'order_number'       => $this->order_number,
            'total_amount_cents' => $this->total_amount_cents,
            'total_quantity'     => $this->total_quantity,
            'status'             => $this->status,
            'payment_status'     => $this->payment_status ?? 'pending_payment',
            'address'            => $this->address,
            'customer'           => $this->whenLoaded('user', fn () => [
                'id'    => $this->user->id,
                'name'  => $this->user->name,
                'email' => $this->user->email,
            ]),
            'items'              => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];
    }
}
