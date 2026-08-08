<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        try {
            $user->refresh();
        } catch (\Throwable $e) {
            return $this->forceLogout($request, 'Tài khoản của bạn không còn tồn tại. Bạn sẽ bị đăng xuất.');
        }

        if ((int) ($user->deleted ?? 0) === 1) {
            return $this->forceLogout($request, 'Tài khoản của bạn đã bị xóa. Bạn sẽ bị đăng xuất.');
        }

        if (isset($user->is_active) && !$user->is_active) {
            return $this->forceLogout($request, 'Tài khoản của bạn đã bị vô hiệu hóa. Bạn sẽ bị đăng xuất.');
        }

        return $next($request);
    }

    private function forceLogout(Request $request, string $message): Response
    {
        Auth::logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($this->wantsJson($request)) {
            return response()->json([
                'force_logout' => true,
                'message' => $message,
            ], 403);
        }

        return redirect()
            ->route('login')
            ->with('force_logout_message', $message);
    }

    private function wantsJson(Request $request): bool
    {
        return $request->expectsJson()
            || $request->ajax()
            || $request->is('api/*')
            || $request->is('account/status')
            || $request->is('guard/webrtc/*');
    }
}
