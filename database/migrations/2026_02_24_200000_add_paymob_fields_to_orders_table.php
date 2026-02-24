<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('paymob_order_id')->nullable()->index()->after('status');
            $table->string('transaction_id')->nullable()->after('paymob_order_id');
            $table->text('payment_url')->nullable()->after('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['paymob_order_id', 'transaction_id', 'payment_url']);
        });
    }
};

