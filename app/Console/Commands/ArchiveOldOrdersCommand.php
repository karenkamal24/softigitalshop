<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ArchiveOldOrdersCommand extends Command
{
    protected $signature = 'orders:archive';

    protected $description = 'Archive orders older than three years';

    public function handle(): int
    {
        $threshold = now()->subYears(3);
        $chunkSize = 500;
        $totalArchived = 0;

        $query = Order::query()
            ->where('created_at', '<', $threshold)
            ->whereNull('archived_at');

        $query->chunkById($chunkSize, function ($orders) use (&$totalArchived): void {
            $ids = $orders->pluck('id')->toArray();
            Order::withoutGlobalScope('active')
                ->whereIn('id', $ids)
                ->update(['archived_at' => now()]);
            $totalArchived += count($ids);
        });

        if ($totalArchived > 0) {
            Log::info('Order housekeeping completed', [
                'archived_count' => $totalArchived,
                'threshold_date' => $threshold->toIso8601String(),
            ]);
        }

        $this->info("Archived {$totalArchived} orders older than three years.");

        return self::SUCCESS;
    }
}
