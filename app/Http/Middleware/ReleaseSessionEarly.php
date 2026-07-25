<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Nhả file-session ngay sau khi auth, để LIVE/poll song song trên ĐT không bị treo.
 */
class ReleaseSessionEarly
{
    public function handle(Request $request, Closure $next)
    {
        try {
            if ($request->hasSession() && $request->session()->isStarted()) {
                $request->session()->save();
                $handler = $request->session()->getHandler();
                if (method_exists($handler, 'close')) {
                    $handler->close();
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return $next($request);
    }
}
