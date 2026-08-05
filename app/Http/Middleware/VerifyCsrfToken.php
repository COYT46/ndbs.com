<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    // CSRF: các endpoint ĐT gọi liên tục
    protected $except = [
        'api/recognize-preview',
        'api/detect-preview',
        'api/live-preview',
        'api/armed-exit-code',
        'api/guard-monitor',
    ];

    /**
     * Đồng bộ Secure với session cookie (tránh XSRF Secure=true trong khi session Secure=false).
     */
    protected function newCookie($request, $config)
    {
        $config['secure'] = (bool) ($config['secure'] ?? false);

        return parent::newCookie($request, $config);
    }
}
