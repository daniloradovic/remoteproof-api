<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('app.admin_emails', ['admin@remoteproof.app']);
});

it('allows allowlisted users into the admin panel', function () {
    $user = User::factory()->create(['email' => 'admin@remoteproof.app']);

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();
});

it('denies non-allowlisted users from the admin panel', function () {
    $user = User::factory()->create(['email' => 'someone@else.com']);

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

it('redirects guests to the admin login page', function () {
    $this->get('/admin')
        ->assertRedirect('/admin/login');
});
