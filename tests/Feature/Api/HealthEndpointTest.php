<?php

declare(strict_types=1);

use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('api');
});

it('returns ok with the configured version and timestamp', function () {
    config()->set('app.version', 'abc1234');

    $response = $this->getJson('/api/health');

    $response
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('version', 'abc1234');

    expect($response->json('time'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
});

it('falls back to unknown when APP_VERSION is not set', function () {
    config()->set('app.version', 'unknown');

    $this->getJson('/api/health')
        ->assertOk()
        ->assertJsonPath('version', 'unknown');
});

it('is not rate limited', function () {
    for ($i = 0; $i < 100; $i++) {
        $this->getJson('/api/health')->assertOk();
    }
});
