<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Luôn bám host/scheme của request hiện tại (ndbs.com vs IP trong APP_URL),
        // để AJAX từ ĐT/PC không gọi nhầm host → mất cookie → không nhận mã quẹt thẻ.
        if (!app()->runningInConsole()) {
            $request = request();
            $root = $request->getSchemeAndHttpHost();
            if (!empty($root)) {
                URL::forceRootUrl($root);
            }
            if ($request->isSecure() || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)) {
                URL::forceScheme('https');
            }
        }
    }
}
