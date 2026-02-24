<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Admin;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Utils\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) ($request->query('per_page') ?? 15);

        if ($request->user() instanceof Admin) {
            // Admin sees all orders
            $orders = Order::with(['items', 'user'])->latest()->paginate($perPage);

            return ApiResponse::success('All orders retrieved successfully', $orders, OrderResource::class);
        }

        /** @var User $user */
        $user = $request->user();

        // Regular customer sees only their own orders
        $orders = $user->orders()->with('items')->latest()->paginate($perPage);

        return ApiResponse::success('Your orders retrieved successfully', $orders, OrderResource::class);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validated();

        $result = $this->orderService->placeOrder(
            $user,
            $validated['items'],
            $validated['address'] ?? null,
        );

        $order = $result['order'];
        $paymentUrl = $result['payment_url'];

        $data = (new OrderResource($order))->toArray($request);
        if ($paymentUrl !== null) {
            $data['payment_url'] = $paymentUrl;
        }

        return ApiResponse::created('Order placed successfully', $data);
    }
}




