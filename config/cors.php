<?php

declare(strict_types=1);

$splitOrigins = static function (?string $value): array {
    if ($value === null || trim($value) === '') {
        return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $origin): bool => $origin !== ''));
};

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Origins are read from EXTENSION_ORIGIN and LANDING_ORIGIN, each of which
    | accepts a comma-separated list. When both are empty no cross-origin
    | request is allowed — the API is fail-closed by default.
    |
    */

    'paths' => ['api/*'],

    'allowed_methods' => ['POST', 'GET', 'OPTIONS'],

    'allowed_origins' => [
        ...$splitOrigins(env('EXTENSION_ORIGIN')),
        ...$splitOrigins(env('LANDING_ORIGIN')),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Accept', 'X-Anon-Id'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
