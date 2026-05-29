<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Behind Railway's edge, TLS terminates at the proxy and the edge->container
        // hop is plain HTTP carrying X-Forwarded-Proto: https. We deliberately trust
        // only private proxy ranges (see bootstrap/app.php) so clients can't spoof
        // their IP — but that means the forwarded-proto header is no longer honored,
        // so the request reads as http and @vite/asset() emit http:// URLs that the
        // browser blocks as mixed content. Force the scheme in production to keep
        // URL generation correct without loosening proxy trust (a separate concern,
        // governing $request->ip() for the rate limiter, not the URL scheme).
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Gate::define('viewPulse', function ($user): bool {
            return $user !== null
                && in_array($user->email, config('app.admin_emails', []), true);
        });

        RateLimiter::for('classify', function (Request $request): array {
            $ip = $request->ip() ?? 'no-ip';
            $anonId = substr((string) $request->header('X-Anon-Id', ''), 0, 64);

            $limits = [Limit::perMinute(10)->by('classify:ip:'.$ip)];

            if ($anonId !== '') {
                $limits[] = Limit::perMinute(10)->by('classify:anon:'.$anonId);
            }

            return $limits;
        });
    }
}
