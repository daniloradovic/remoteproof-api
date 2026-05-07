<?php

declare(strict_types=1);

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists an event with all the expected fields', function () {
    $event = Event::create([
        'anon_id' => 'a1b2c3d4',
        'name' => 'classify.completed',
        'host' => 'linkedin.com',
        'verdict' => 'WORLDWIDE',
        'cached' => false,
        'latency_ms' => 1234,
        'properties' => ['foo' => 'bar'],
        'created_at' => now(),
    ]);

    expect($event->fresh())
        ->anon_id->toBe('a1b2c3d4')
        ->name->toBe('classify.completed')
        ->host->toBe('linkedin.com')
        ->verdict->toBe('WORLDWIDE')
        ->cached->toBeFalse()
        ->latency_ms->toBe(1234)
        ->properties->toBe(['foo' => 'bar']);
});

it('does not maintain an updated_at column', function () {
    $event = Event::create([
        'anon_id' => 'x',
        'name' => 'noop',
        'created_at' => now(),
    ]);

    expect($event->getAttributes())->not->toHaveKey('updated_at');
});

it('allows nullable host, verdict, latency_ms and properties', function () {
    $event = Event::create([
        'anon_id' => 'x',
        'name' => 'classify.completed',
        'created_at' => now(),
    ]);

    expect($event->fresh())
        ->host->toBeNull()
        ->verdict->toBeNull()
        ->latency_ms->toBeNull()
        ->properties->toBeNull()
        ->cached->toBeFalse();
});
