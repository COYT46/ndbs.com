<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['web', 'auth', 'account.active', 'session.release'])->group(function () {
    Route::middleware('throttle:api')->group(function () {
        Route::post('/recognize-preview', [ApiController::class, 'recognizePreview'])->name('api.recognize_preview');
        Route::post('/detect-preview', [ApiController::class, 'detectPreview'])->name('api.detect_preview');
        Route::post('/recognize-entry', [ApiController::class, 'recognizeEntry'])->name('api.recognize_entry');
        Route::post('/checkout-exit', [ApiController::class, 'checkoutExit'])->name('api.checkout_exit');
        Route::post('/validate-checkout', [ApiController::class, 'validateCheckout'])->name('api.validate_checkout');
        Route::post('/retry-exit', [ApiController::class, 'retryExitAttempt'])->name('api.retry_exit');
        Route::get('/recent-logs', [ApiController::class, 'getRecentLogs'])->name('api.recent_logs');
        Route::post('/arm-exit-code', [ApiController::class, 'armExitCode'])->name('api.arm_exit_code');
        Route::post('/clear-exit-code', [ApiController::class, 'clearExitCode'])->name('api.clear_exit_code');
        Route::post('/manual-confirm-entry', [ApiController::class, 'manualConfirmEntry'])->name('api.manual_confirm_entry');
        Route::post('/manual-confirm-exit', [ApiController::class, 'manualConfirmExit'])->name('api.manual_confirm_exit');
        Route::post('/lookup-monthly-ticket', [ApiController::class, 'lookupMonthlyTicket'])->name('api.lookup_monthly_ticket');
        Route::post('/arm-monthly-code', [ApiController::class, 'armMonthlyCode'])->name('api.arm_monthly_code');
        Route::post('/clear-monthly-code', [ApiController::class, 'clearMonthlyCode'])->name('api.clear_monthly_code');
        Route::post('/validate-monthly-entry', [ApiController::class, 'validateMonthlyEntry'])->name('api.validate_monthly_entry');
        Route::post('/dismiss-pending-monthly-entry', [ApiController::class, 'dismissPendingMonthlyEntry'])->name('api.dismiss_pending_monthly_entry');
        Route::post('/scan-cooldown', [ApiController::class, 'scanCooldown'])->name('api.scan_cooldown');
        Route::post('/scan-hold-ack', [ApiController::class, 'ackScanHold'])->name('api.scan_hold_ack');
    });

    // LIVE + poll mã: gọi liên tục, đã nhả session sớm
    Route::post('/live-preview', [ApiController::class, 'uploadLivePreview'])->name('api.live_preview');
    Route::get('/live-status', [ApiController::class, 'livePreviewStatus'])->name('api.live_status');
    Route::get('/guard-monitor', [ApiController::class, 'guardMonitorState'])->name('api.guard_monitor');
    Route::get('/armed-exit-code', [ApiController::class, 'armedExitCodeStatus'])->name('api.armed_exit_code');
    Route::get('/armed-monthly-code', [ApiController::class, 'armedMonthlyCodeStatus'])->name('api.armed_monthly_code');
    Route::get('/scan-hold', [ApiController::class, 'scanHoldStatus'])->name('api.scan_hold');
});
