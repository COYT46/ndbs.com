<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['web', 'auth', 'session.release'])->group(function () {
    Route::middleware('throttle:api')->group(function () {
        Route::post('/recognize-preview', [ApiController::class, 'recognizePreview'])->name('api.recognize_preview');
        Route::post('/detect-preview', [ApiController::class, 'detectPreview'])->name('api.detect_preview');
        Route::post('/recognize-entry', [ApiController::class, 'recognizeEntry'])->name('api.recognize_entry');
        Route::post('/checkout-exit', [ApiController::class, 'checkoutExit'])->name('api.checkout_exit');
        Route::post('/validate-checkout', [ApiController::class, 'validateCheckout'])->name('api.validate_checkout');
        Route::get('/recent-logs', [ApiController::class, 'getRecentLogs'])->name('api.recent_logs');
        Route::post('/arm-exit-code', [ApiController::class, 'armExitCode'])->name('api.arm_exit_code');
        Route::post('/clear-exit-code', [ApiController::class, 'clearExitCode'])->name('api.clear_exit_code');
    });

    // LIVE + poll mã: gọi liên tục, đã nhả session sớm
    Route::post('/live-preview', [ApiController::class, 'uploadLivePreview'])->name('api.live_preview');
    Route::get('/guard-monitor', [ApiController::class, 'guardMonitorState'])->name('api.guard_monitor');
    Route::get('/armed-exit-code', [ApiController::class, 'armedExitCodeStatus'])->name('api.armed_exit_code');
});
