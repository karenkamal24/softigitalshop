<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/api/documentation', function () {
    return view('swagger');
})->name('api.documentation');

Route::get('/mock-payment', function () {
    $token = request()->query('token', '');
    $isMock = str_starts_with($token, 'mock_');

    return response()->json([
        'status' => 'success',
        'message' => $isMock ? 'Mock payment completed successfully (development only)' : 'Payment completed',
        'token' => $token,
        'note' => 'This is a simulated payment page. With real Paymob, user would enter card details here.',
    ], 200);
})->name('mock-payment');
