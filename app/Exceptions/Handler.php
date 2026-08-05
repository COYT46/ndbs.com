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

        // ĐT hay double-submit login: lần 1 OK (regenerate CSRF), lần 2 → 419 đè mất redirect.
        $this->renderable(function (TokenMismatchException $e, $request) {
            if (Auth::check()) {
                $user = Auth::user();
                if (($user->role ?? null) === 'manager') {
                    return redirect()->intended('/manager/dashboard');
                }

                return redirect()->intended('/guard/dashboard');
            }

            if ($request->is('login') || $request->is('logout')) {
                return redirect()
                    ->route('login')
                    ->withErrors(['email' => 'Phiên đăng nhập đã hết hạn. Vui lòng thử lại.']);
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'CSRF token mismatch.'], 419);
            }

            return redirect()
                ->back()
                ->withInput($request->except($this->dontFlash))
                ->withErrors(['email' => 'Phiên đã hết hạn. Vui lòng thử lại.']);
        });
    }
}
