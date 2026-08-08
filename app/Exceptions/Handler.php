<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // ĐT: CSRF cũ khi đổi tài khoản / double-submit → tránh trang 419.
        $this->renderable(function (TokenMismatchException $e, $request) {
            // Đăng xuất bằng token cũ: vẫn cho thoát về login để đổi tài khoản
            if ($request->is('logout')) {
                try {
                    Auth::logout();
                    if ($request->hasSession()) {
                        $request->session()->invalidate();
                        $request->session()->regenerateToken();
                    }
                } catch (\Throwable $ex) {
                    // ignore
                }

                return redirect()
                    ->route('login')
                    ->with('force_logout_message', 'Phiên cũ đã hết hạn. Vui lòng đăng nhập lại.');
            }

            // Đăng nhập: lần 2 double-submit sau khi lần 1 đã OK
            if ($request->is('login') && Auth::check()) {
                $user = Auth::user();
                if (($user->role ?? null) === 'manager') {
                    return redirect()->intended('/manager/dashboard');
                }

                return redirect()->intended('/guard/dashboard');
            }

            if ($request->is('login')) {
                return redirect()
                    ->route('login')
                    ->withErrors(['email' => 'Phiên đăng nhập đã hết hạn. Vui lòng thử lại.']);
            }

            if ($request->expectsJson() || $request->ajax() || $request->is('api/*') || $request->is('guard/webrtc/*')) {
                return response()->json([
                    'message' => 'Phiên đã hết hạn. Vui lòng tải lại trang hoặc đăng nhập lại.',
                    'force_logout' => !Auth::check(),
                ], 419);
            }

            if (Auth::check()) {
                return redirect()
                    ->back()
                    ->withInput($request->except($this->dontFlash))
                    ->with('force_logout_message', 'Phiên đã hết hạn. Vui lòng thử lại.');
            }

            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Phiên đã hết hạn. Vui lòng đăng nhập lại.']);
        });
    }
}
