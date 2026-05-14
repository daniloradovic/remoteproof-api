<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
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
    }
}
