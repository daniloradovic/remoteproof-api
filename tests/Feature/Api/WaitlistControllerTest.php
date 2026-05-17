<?php

declare(strict_types=1);

use App\Models\WaitlistSignup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('api');
});

it('accepts a valid email and records the signup', function () {
    $response = $this->postJson('/api/waitlist', [
        'email' => 'someone@example.com',
        'plan_interest' => 'pro',
        'source' => 'landing_pricing',
    ]);

    $response
        ->assertOk()
        ->assertExactJson([
            'ok' => true,
            'already_signed_up' => false,
        ]);

    $row = WaitlistSignup::firstWhere('email', 'someone@example.com');
    expect($row)->not->toBeNull();
    expect($row->plan_interest)->toBe('pro');
    expect($row->source)->toBe('landing_pricing');
});

it('lowercases and trims the submitted email', function () {
    $this->postJson('/api/waitlist', [
        'email' => '  Someone@Example.com  ',
    ])->assertOk();

    expect(WaitlistSignup::where('email', 'someone@example.com')->exists())->toBeTrue();
});

it('flags duplicate signups but still records them', function () {
    WaitlistSignup::create([
        'email' => 'dupe@example.com',
        'plan_interest' => 'pro',
        'source' => 'landing_pricing',
    ]);

    $response = $this->postJson('/api/waitlist', [
        'email' => 'dupe@example.com',
        'plan_interest' => 'pro',
        'source' => 'quota_429',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('already_signed_up', true);

    expect(WaitlistSignup::where('email', 'dupe@example.com')->count())->toBe(2);
});

it('rejects a missing email', function () {
    $this->postJson('/api/waitlist', [])
        ->assertStatus(422)
        ->assertJsonPath('error', 'Email is required.');
});

it('rejects a malformed email', function () {
    $this->postJson('/api/waitlist', ['email' => 'not-an-email'])
        ->assertStatus(422)
        ->assertJsonPath('error', 'Please enter a valid email address.');
});

it('rejects an unknown plan_interest value', function () {
    $this->postJson('/api/waitlist', [
        'email' => 'someone@example.com',
        'plan_interest' => 'enterprise',
    ])->assertStatus(422);
});

it('captures anon id, referrer host, ip, and user agent', function () {
    $this->withHeaders([
        'X-Anon-Id' => 'anon-xyz',
        'Referer' => 'https://remoteproof.app/some/page?foo=bar',
        'User-Agent' => 'TestRunner/1.0',
    ])->postJson('/api/waitlist', [
        'email' => 'tracked@example.com',
        'source' => 'landing_pricing',
    ])->assertOk();

    $row = WaitlistSignup::firstWhere('email', 'tracked@example.com');

    expect($row->anon_id)->toBe('anon-xyz');
    expect($row->host_referrer)->toBe('remoteproof.app');
    expect($row->user_agent)->toBe('TestRunner/1.0');
    expect($row->ip)->not->toBeNull();
});

it('truncates an oversized anon id header to 64 chars', function () {
    $long = str_repeat('a', 200);

    $this->withHeader('X-Anon-Id', $long)
        ->postJson('/api/waitlist', ['email' => 'long@example.com'])
        ->assertOk();

    $row = WaitlistSignup::firstWhere('email', 'long@example.com');
    expect(strlen($row->anon_id))->toBe(64);
});

it('throttles after 5 requests per minute', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/waitlist', [
            'email' => "user{$i}@example.com",
        ])->assertOk();
    }

    $this->postJson('/api/waitlist', ['email' => 'over@example.com'])
        ->assertStatus(429);
});
