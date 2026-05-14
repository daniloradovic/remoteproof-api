<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('viewPulse', function ($user): bool {
            return $user !== null
                && in_array($user->email, config('app.admin_emails', []), true);
        });

        RateLimiter::for('classify', function (Request $request): Limit {
            $anonId = trim((string) $request->header('X-Anon-Id', ''));
            $key = $anonId !== '' ? 'anon:'.substr($anonId, 0, 64) : 'ip:'.$request->ip();

            return Limit::perMinute(60)->by($key);
        });
    }
}
