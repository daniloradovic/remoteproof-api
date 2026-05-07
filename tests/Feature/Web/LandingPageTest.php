<?php

declare(strict_types=1);

it('renders the landing page', function () {
    $response = $this->get('/');

    $response
        ->assertOk()
        ->assertSee('RemoteProof')
        ->assertSee('Add to Chrome')
        ->assertSee('How it works');
});

it('renders the privacy page', function () {
    $response = $this->get('/privacy');

    $response
        ->assertOk()
        ->assertSee('Privacy')
        ->assertSee('Anonymous UUID')
        ->assertSee('hello@remoteproof.app');
});

it('exposes named routes for landing and privacy', function () {
    expect(route('landing', absolute: false))->toBe('/')
        ->and(route('privacy', absolute: false))->toBe('/privacy');
});
