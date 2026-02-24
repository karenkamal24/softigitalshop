<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\PaymobCallbackController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    require __DIR__ . '/api/v1/auth.php';
    require __DIR__ . '/api/v1/products.php';
    require __DIR__ . '/api/v1/orders.php';
    require __DIR__ . '/api/v1/media.php';
    require __DIR__ . '/api/v1/admin.php';


    Route::post('/paymob/webhook', [PaymobCallbackController::class, 'webhook'])
        ->name('paymob.webhook');

    Route::get('/paymob/response', [PaymobCallbackController::class, 'response'])
        ->name('paymob.response');
});
