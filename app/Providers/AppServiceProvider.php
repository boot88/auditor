<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}
    
    public function boot(): void
    {
        RateLimiter::for('audit', function (Request $request) {
            $perHour = (int) env('AUDIT_RATE_LIMIT_PER_IP', 10);
            return Limit::perHour($perHour)->by($request->ip());
        });
    }
}
