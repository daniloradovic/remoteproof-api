<?php

declare(strict_types=1);

use App\Filament\Pages\AnonUsage;
use App\Filament\Resources\Events\Pages\ListEvents;
use App\Http\Controllers\Api\ClassifyController;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('app.admin_emails', ['admin@remoteproof.app']);
    Cache::flush();

    $this->actingAs(User::factory()->create(['email' => 'admin@remoteproof.app']));
});

it('AnonUsage row action clears the per-anon monthly counter for current month', function () {
    $anonId = 'anon-xyz';
    $month = now()->format('Y-m');
    $key = ClassifyController::ANON_MONTHLY_SPEND_PREFIX.$anonId.':'.$month;

    Cache::put($key, 42, now()->endOfMonth());
    expect(Cache::get($key))->toBe(42);

    $event = Event::create([
        'anon_id' => $anonId,
        'name' => 'classify.completed',
        'verdict' => 'WORLDWIDE',
        'cached' => false,
        'created_at' => now(),
    ]);

    Livewire::test(AnonUsage::class)
        ->callTableAction('resetAnonUsage', $event->id)
        ->assertHasNoErrors();

    expect(Cache::has($key))->toBeFalse();
});

it('header action clears the cached classification for a given URL', function () {
    $url = 'https://www.linkedin.com/jobs/view/12345';
    $key = ClassifyController::CACHE_KEY_PREFIX.sha1($url);

    Cache::put($key, ['verdict' => 'WORLDWIDE'], now()->addHours(24));
    expect(Cache::has($key))->toBeTrue();

    Livewire::test(ListEvents::class)
        ->callAction('clearUrlCache', data: ['url' => $url])
        ->assertHasNoErrors();

    expect(Cache::has($key))->toBeFalse();
});

it('header action succeeds gracefully when URL has no cached entry', function () {
    Livewire::test(ListEvents::class)
        ->callAction('clearUrlCache', data: ['url' => 'https://example.com/nonexistent'])
        ->assertHasNoErrors();
});
