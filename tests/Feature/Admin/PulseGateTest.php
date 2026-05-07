<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('app.admin_emails', ['admin@remoteproof.app']);
});

it('allows allowlisted users to view pulse', function () {
    $user = User::factory()->create(['email' => 'admin@remoteproof.app']);

    expect(Gate::forUser($user)->allows('viewPulse'))->toBeTrue();
});

it('denies non-allowlisted users', function () {
    $user = User::factory()->create(['email' => 'someone@else.com']);

    expect(Gate::forUser($user)->allows('viewPulse'))->toBeFalse();
});

it('denies unauthenticated guests', function () {
    expect(Gate::allows('viewPulse'))->toBeFalse();
});

it('denies everyone when the allowlist is empty', function () {
    config()->set('app.admin_emails', []);

    $user = User::factory()->create(['email' => 'admin@remoteproof.app']);

    expect(Gate::forUser($user)->allows('viewPulse'))->toBeFalse();
});
