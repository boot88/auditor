<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('audit', function (Request $request) {
            $perHour = (int) env('AUDIT_RATE_LIMIT_PER_IP', 10);
            return Limit::perHour($perHour)->by($request->ip());
        });
            
            parent::boot();
    }
}
