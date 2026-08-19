<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ManagerController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/login', [AuthController::class, 'getLogin'])->name('login');
Route::post('/login', [AuthController::class, 'postLogin']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
// ĐT đổi tài khoản: lấy CSRF mới trước khi submit login/logout (tránh 419)
Route::get('/csrf-token', function () {
    return response()->json([
        'token' => csrf_token(),
    ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
})->name('csrf.token');

Route::middleware(['auth', 'account.active'])->group(function () {
    Route::get('/account/status', function () {
        return response()->json(['ok' => true]);
    })->name('account.status');

    // Manager Routes
    Route::prefix('manager')->group(function () {
        Route::get('/dashboard', [ManagerController::class, 'dashboard'])->name('manager.dashboard');
        Route::post('/guard', [ManagerController::class, 'storeGuard'])->name('manager.guard.store');
        Route::put('/guard/{id}', [ManagerController::class, 'updateGuard'])->name('manager.guard.update');
        Route::patch('/guard/{id}/toggle-status', [ManagerController::class, 'toggleStatusGuard'])->name('manager.guard.toggle_status');
        Route::delete('/guard/{id}', [ManagerController::class, 'deleteGuard'])->name('manager.guard.delete');
        Route::get('/ticket-prices', [ManagerController::class, 'ticketPrices'])->name('manager.ticket_prices');
        Route::post('/ticket-prices', [ManagerController::class, 'saveTicketPrices'])->name('manager.ticket_prices.save');
        Route::get('/monthly-tickets', [\App\Http\Controllers\MonthlyTicketController::class, 'index'])->name('manager.monthly_tickets');
        Route::post('/monthly-tickets', [\App\Http\Controllers\MonthlyTicketController::class, 'store'])->name('manager.monthly_tickets.store');
        Route::put('/monthly-tickets/{id}', [\App\Http\Controllers\MonthlyTicketController::class, 'update'])->name('manager.monthly_tickets.update');
        Route::patch('/monthly-tickets/{id}/toggle-status', [\App\Http\Controllers\MonthlyTicketController::class, 'toggleStatus'])->name('manager.monthly_tickets.toggle_status');
        Route::delete('/monthly-tickets/{id}', [\App\Http\Controllers\MonthlyTicketController::class, 'destroy'])->name('manager.monthly_tickets.delete');
        Route::post('/monthly-tickets/{id}/renew', [\App\Http\Controllers\MonthlyTicketController::class, 'renew'])->name('manager.monthly_tickets.renew');
        Route::get('/vehicle-logs', [ManagerController::class, 'vehicleLogs'])->name('manager.vehicle_logs');
        Route::get('/monthly-vehicle-logs', [ManagerController::class, 'monthlyVehicleLogs'])->name('manager.monthly_vehicle_logs');
    });

    // Guard Routes
    Route::prefix('guard')->group(function () {
        Route::get('/dashboard', function () {
            if (auth()->user()->role !== 'guard') {
                return redirect('/');
            }
            return view('guard.dashboard');
        })->name('guard.dashboard');

        // Trang nhận diện bằng ảnh (upload)
        Route::get('/recognize', function () {
            if (auth()->user()->role !== 'guard') {
                return redirect('/');
            }
            return view('guard.recognize');
        })->name('guard.recognize');

        // Trang quét ĐT: mở là tự camera + tự nhận diện (không bấm)
        Route::get('/scan/{side}', function ($side) {
            if (auth()->user()->role !== 'guard') {
                return redirect('/');
            }
            $side = $side === 'exit' ? 'exit' : 'entry';
            return view('guard.scan', compact('side'));
        })->name('guard.scan');

        // WebRTC signaling — web session, nhả sớm (không dùng Sanctum)
        Route::middleware(['session.release'])->group(function () {
            Route::post('/webrtc/signal', [\App\Http\Controllers\ApiController::class, 'webrtcPostSignal'])
                ->name('guard.webrtc_signal_post');
            Route::get('/webrtc/signal', [\App\Http\Controllers\ApiController::class, 'webrtcPollSignal'])
                ->name('guard.webrtc_signal_poll');
        });
    });
});
