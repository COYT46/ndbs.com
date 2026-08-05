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

Route::middleware(['auth'])->group(function () {
    // Manager Routes
    Route::prefix('manager')->group(function () {
        Route::get('/dashboard', [ManagerController::class, 'dashboard'])->name('manager.dashboard');
        Route::post('/guard', [ManagerController::class, 'storeGuard'])->name('manager.guard.store');
        Route::put('/guard/{id}', [ManagerController::class, 'updateGuard'])->name('manager.guard.update');
        Route::patch('/guard/{id}/toggle-status', [ManagerController::class, 'toggleStatusGuard'])->name('manager.guard.toggle_status');
        Route::delete('/guard/{id}', [ManagerController::class, 'deleteGuard'])->name('manager.guard.delete');
        Route::get('/vehicle-logs', [ManagerController::class, 'vehicleLogs'])->name('manager.vehicle_logs');
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
