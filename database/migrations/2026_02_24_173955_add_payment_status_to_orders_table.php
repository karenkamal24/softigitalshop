<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('payment_status')->default('pending_payment')->after('status');
        });

        $mapping = [
            'pending' => ['payment_status' => 'pending_payment', 'status' => 'pending'],
            'pending_payment' => ['payment_status' => 'pending_payment', 'status' => 'pending'],
            'paid' => ['payment_status' => 'paid', 'status' => 'confirmed'],
            'payment_failed' => ['payment_status' => 'payment_failed', 'status' => 'pending'],
            'confirmed' => ['payment_status' => 'paid', 'status' => 'confirmed'],
            'shipped' => ['payment_status' => 'paid', 'status' => 'shipped'],
            'delivered' => ['payment_status' => 'paid', 'status' => 'delivered'],
            'refunded' => ['payment_status' => 'refunded', 'status' => 'cancelled'],
            'failed' => ['payment_status' => 'payment_failed', 'status' => 'pending'],
        ];

        foreach ($mapping as $oldStatus => $newValues) {
            DB::table('orders')->where('status', $oldStatus)->update($newValues);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('payment_status');
        });
    }
};
