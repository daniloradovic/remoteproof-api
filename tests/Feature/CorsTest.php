<?php

declare(strict_types=1);

beforeEach(function () {
    config()->set('cors.allowed_origins', [
        'chrome-extension://abc123',
        'https://remoteproof.app',
    ]);
});

it('allows preflight from an allowlisted origin', function () {
    $response = $this->call('OPTIONS', '/api/classify', [], [], [], [
        'HTTP_ORIGIN' => 'chrome-extension://abc123',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,x-anon-id',
    ]);

    $response
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', 'chrome-extension://abc123');
});

it('omits cors headers when origin is not allowlisted', function () {
    $response = $this->call('OPTIONS', '/api/classify', [], [], [], [
        'HTTP_ORIGIN' => 'https://evil.example',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);

    expect($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
});

it('fails closed when no origins are configured', function () {
    config()->set('cors.allowed_origins', []);

    $response = $this->call('OPTIONS', '/api/classify', [], [], [], [
        'HTTP_ORIGIN' => 'chrome-extension://abc123',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);

    expect($response->headers->has('Access-Control-Allow-Origin'))->toBeFalse();
});
