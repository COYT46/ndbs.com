<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('web')->group(function () {
    Route::post('/recognize-entry', [ApiController::class, 'recognizeEntry'])->name('api.recognize_entry');
    Route::post('/checkout-exit', [ApiController::class, 'checkoutExit'])->name('api.checkout_exit');
    Route::post('/validate-checkout', [ApiController::class, 'validateCheckout'])->name('api.validate_checkout');
    Route::get('/recent-logs', [ApiController::class, 'getRecentLogs'])->name('api.recent_logs');
});
