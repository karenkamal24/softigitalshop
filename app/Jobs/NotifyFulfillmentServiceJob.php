<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Services\FulfillmentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotifyFulfillmentServiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 10;

    /** @var array<int, int> */
    public array $backoff = [5, 15, 30, 60, 120, 300, 600, 1200, 1800, 3600];

    public function __construct(
        public readonly Order $order,
    ) {}

    public function handle(FulfillmentService $fulfillmentService): void
    {
        $user = $this->order->user;

        $fulfillmentService->notifyOrder([
            'order_id' => $this->order->order_number,
            'amount_in_cents' => $this->order->total_amount_cents,
            'total_quantity' => $this->order->total_quantity,
            'customer_name' => $user->name,
            'address' => $this->order->address,
        ]);

        Log::info('Fulfillment service notified for order: ' . $this->order->order_number);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to notify fulfillment service for order: ' . $this->order->order_number, [
            'error' => $exception->getMessage(),
        ]);
    }
}
