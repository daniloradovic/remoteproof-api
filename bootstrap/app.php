<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust only the proxy hop, never the client. With `'*'` Symfony takes the
        // leftmost (client-supplied) X-Forwarded-For entry, which lets an attacker
        // forge their IP and walk straight past the per-IP rate limit and daily cap.
        // Private ranges are safe to trust as proxies because no public client can
        // originate a connection from one — on Railway the edge->container hop rides
        // the private network. Override via TRUSTED_PROXIES (comma-separated CIDRs)
        // to pin a specific edge range; set it to `*` only as a deliberate escape hatch.
        $configured = trim((string) env('TRUSTED_PROXIES', ''));

        $proxies = $configured === ''
            ? ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '127.0.0.1', 'fc00::/7', '::1']
            : ($configured === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $configured)))));

        $middleware->trustProxies(at: $proxies);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);
    })->create();
