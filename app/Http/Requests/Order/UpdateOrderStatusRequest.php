<?php

declare(strict_types=1);

namespace App\Http\Requests\Order;

use App\Enums\OrderStatus;
use App\Rules\OrderStatusTransition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        /** @var \App\Models\Order $order */
        $order = $this->route('order');

        $allowedStatuses = [OrderStatus::Shipped->value, OrderStatus::Delivered->value];

        return [
            'status' => [
                'required',
                'string',
                Rule::in($allowedStatuses),
                new OrderStatusTransition($order),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status.required' => 'Order status is required.',
            'status.in' => 'Status must be either shipped or delivered.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'status' => 'order status',
        ];
    }
}
