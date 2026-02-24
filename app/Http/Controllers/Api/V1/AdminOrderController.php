<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->query('per_page') ?? 15);
        $orders = Order::with(['items', 'user'])->latest()->paginate($perPage);

        return ApiResponse::success('Orders retrieved successfully', $orders, OrderResource::class);
    }

    public function show(Order $order): JsonResponse
    {
        $order->load(['items', 'user']);

        return ApiResponse::success('Order retrieved successfully', new OrderResource($order));
    }

    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $order->update(['status' => $request->validated('status')]);

        return ApiResponse::success('Order status updated successfully', new OrderResource($order->load(['items', 'user'])));
    }
}

