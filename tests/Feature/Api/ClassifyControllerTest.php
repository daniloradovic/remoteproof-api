<?php

declare(strict_types=1);

use App\Models\Event;
use App\Services\ClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.anthropic.key', 'test-key');
    config()->set('services.anthropic.url', 'https://api.anthropic.test/v1/messages');
    config()->set('services.anthropic.model', 'claude-sonnet-4-20250514');
    config()->set('services.anthropic.max_tokens', 1024);

    // Default caps high enough that the unrelated tests don't trip them.
    // Tests that exercise the caps override these explicitly.
    config()->set('services.anthropic.daily_cap', 10_000);
    config()->set('services.anthropic.monthly_cap', 100_000);
    config()->set('services.anthropic.per_anon_monthly_cap', 10_000);
    config()->set('services.anthropic.per_ip_daily_cap', 10_000);

    Cache::flush();
});

function fakeAnthropicHttpResponse(array $payload): array
{
    return [
        'id' => 'msg_test',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-sonnet-4-20250514',
        'content' => [
            [
                'type' => 'text',
                'text' => json_encode($payload, JSON_THROW_ON_ERROR),
            ],
        ],
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
    ];
}

function longJobDescription(string $extra = ''): string
{
    $base = str_repeat('This is a long job description for a fully remote engineering role. ', 5);

    return $base.$extra;
}

it('returns a structured classification response on success', function () {
    Http::fake([
        'https://api.anthropic.test/*' => Http::response(fakeAnthropicHttpResponse([
            'verdict' => 'RESTRICTED',
            'confidence' => 'HIGH',
            'reason' => 'Requires US work authorization.',
            'signals' => ['must be authorized to work in the US'],
        ])),
    ]);

    $response = $this->postJson('/api/classify', [
        'text' => longJobDescription('Must be authorized to work in the US.'),
    ]);

    $response
        ->assertOk()
        ->assertExactJson([
            'verdict' => 'RESTRICTED',
            'confidence' => 'HIGH',
            'reason' => 'Requires US work authorization.',
            'signals' => ['must be authorized to work in the US'],
            'cached' => false,
        ]);
});

it('accepts an optional url alongside the text', function () {
    Http::fake([
        'https://api.anthropic.test/*' => Http::response(fakeAnthropicHttpResponse([
            'verdict' => 'WORLDWIDE',
            'confidence' => 'HIGH',
            'reason' => 'Hiring globally with Deel.',
            'signals' => ['Deel mentioned'],
        ])),
    ]);

    $response = $this->postJson('/api/classify', [
        'text' => longJobDescription('We hire worldwide via Deel.'),
        'url' => 'https://linkedin.com/jobs/view/123456',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('verdict', 'WORLDWIDE')
        ->assertJsonPath('cached', false);
});

it('passes the validated text to the classification service', function () {
    Http::fake([
        'https://api.anthropic.test/*' => Http::response(fakeAnthropicHttpResponse([
            'verdict' => 'UNCLEAR',
            'confidence' => 'MEDIUM',
            'reason' => 'No geographic context.',
            'signals' => [],
        ])),
    ]);

    $captured = null;

    $this->mock(ClassificationService::class, function ($mock) use (&$captured) {
        $mock->shouldReceive('classify')
            ->once()
            ->andReturnUsing(function (string $text) use (&$captured) {
                $captured = $text;

                return [
                    'verdict' => 'UNCLEAR',
                    'confidence' => 'MEDIUM',
                    'reason' => 'No geographic context.',
                    'signals' => [],
                ];
            });
    });

    $payload = longJobDescription('Mentions remote with no specifics.');

    $this->postJson('/api/classify', [
        'text' => $payload,
    ])->assertOk();

    expect($captured)->toBe($payload);
});

it('rejects requests without a text field', function () {
    $response = $this->postJson('/api/classify', []);

    $response
        ->assertStatus(422)
        ->assertExactJson([
            'error' => 'The text field is required and must be at least 100 characters.',
        ]);
});

it('rejects requests where text is shorter than 100 characters', function () {
    $response = $this->postJson('/api/classify', [
        'text' => 'too short',
    ]);

    $response
        ->assertStatus(422)
        ->assertExactJson([
            'error' => 'The text field is required and must be at least 100 characters.',
        ]);
});

it('rejects requests where text is not a string', function () {
    $response = $this->postJson('/api/classify', [
        'text' => 12345,
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonPath('error', 'The text field is required and must be at least 100 characters.');
});

it('rejects requests when the optional url is malformed', function () {
    $response = $this->postJson('/api/classify', [
        'text' => longJobDescription(),
        'url' => 'not-a-real-url',
    ]);

    $response
        ->assertStatus(422)
        ->assertExactJson([
            'error' => 'The url field must be a valid URL.',
        ]);
});

it('returns a 502 when the classification service throws', function () {
    Log::spy();

    $this->mock(ClassificationService::class, function ($mock) {
        $mock->shouldReceive('classify')
            ->once()
            ->andThrow(new RuntimeException('Anthropic API request failed with status 503'));
    });

    $response = $this->postJson('/api/classify', [
        'text' => longJobDescription(),
    ]);

    $response
        ->assertStatus(502)
        ->assertExactJson([
            'error' => 'Failed to classify the job description.',
        ]);

    Log::shouldHaveReceived('error')->once();
});

it('does not call the classification service when validation fails', function () {
    $mock = $this->mock(ClassificationService::class);
    $mock->shouldNotReceive('classify');

    $this->postJson('/api/classify', [
        'text' => 'short',
    ])->assertStatus(422);
});

it('returns cached result on a second request with the same url', function () {
    Http::fake([
        'https://api.anthropic.test/*' => Http::response(fakeAnthropicHttpResponse([
            'verdict' => 'WORLDWIDE',
            'confidence' => 'HIGH',
            'reason' => 'Truly remote.',
            'signals' => ['work from anywhere'],
        ])),
    ]);

    $url = 'https://example.com/jobs/abc';

    $first = $this->postJson('/api/classify', [
        'text' => longJobDescription(),
        'url' => $url,
    ]);

    $first
        ->assertOk()
        ->assertJsonPath('cached', false)
        ->assertJsonPath('verdict', 'WORLDWIDE');

    $second = $this->postJson('/api/classify', [
        'text' => longJobDescription(),
        'url' => $url,
    ]);

    $second
        ->assertOk()
        ->assertExactJson([
            'verdict' => 'WORLDWIDE',
            'confidence' => 'HIGH',
            'reason' => 'Truly remote.',
            'signals' => ['work from anywhere'],
            'cached' => true,
        ]);

    Http::assertSentCount(1);
});

it('does not call the classification service on a cache hit', function () {
    $url = 'https://example.com/jobs/cached';

    Cache::put('classification:url:'.sha1($url), [
        'verdict' => 'RESTRICTED',
        'confidence' => 'HIGH',
        'reason' => 'Requires US work authorization.',
        'signals' => ['must be authorized to work in the US'],
    ], now()->addHours(24));

    $mock = $this->mock(ClassificationService::class);
    $mock->shouldNotReceive('classify');

    $response = $this->postJson('/api/classify', [
        'text' => longJobDescription(),
        'url' => $url,
    ]);

    $response
        ->assertOk()
        ->assertExactJson([
            'verdict' => 'RESTRICTED',
            'confidence' => 'HIGH',
            'reason' => 'Requires US work authorization.',
            'signals' => ['must be authorized to work in the US'],
            'cached' => true,
        ]);
});

it('caches each url independently', function () {
    Http::fakeSequence('https://api.anthropic.test/*')
        ->push(fakeAnthropicHttpResponse([
            'verdict' => 'WORLDWIDE',
            'confidence' => 'HIGH',
            'reason' => 'Hiring globally.',
            'signals' => ['work from anywhere'],
        ]))
        ->push(fakeAnthropicHttpResponse([
            'verdict' => 'RESTRICTED',
            'confidence' => 'HIGH',
            'reason' => 'US only.',
            'signals' => ['US persons only'],
        ]));

    $this->postJson('/api/classify', [
        'text' => longJobDescription('Work from anywhere.'),
        'url' => 'https://example.com/jobs/one',
    ])
        ->assertOk()
        ->assertJsonPath('verdict', 'WORLDWIDE')
        ->assertJsonPath('cached', false);

    $this->postJson('/api/classify', [
        'text' => longJobDescription('Must be a US person.'),
        'url' => 'https://example.com/jobs/two',
    ])
        ->assertOk()
        ->assertJsonPath('verdict', 'RESTRICTED')
        ->assertJsonPath('cached', false);

    Http::assertSentCount(2);
});

it('always calls the api when no url is provided', function () {
    Http::fake([
        'https://api.anthropic.test/*' => Http::response(fakeAnthropicHttpResponse([
            'verdict' => 'UNCLEAR',
            'confidence' => 'MEDIUM',
            'reason' => 'No geographic context.',
            'signals' => [],
        ])),
    ]);

    $payload = ['text' => longJobDescription('Mentions remote with no specifics.')];

    $this->postJson('/api/classify', $payload)
        ->assertOk()
        ->assertJsonPath('cached', false);

    $this->postJson('/api/classify', $payload)
        ->assertOk()
        ->assertJsonPath('cached', false);

    Http::assertSentCount(2);
});

it('does not cache when the classification service throws', function () {
    Log::spy();

    $url = 'https://example.com/jobs/fail';

    $this->mock(ClassificationService::class, function ($mock) {
        $mock->shouldReceive('classify')
            ->once()
            ->andThrow(new RuntimeException('Anthropic API request failed with status 503'));
    });

    $this->postJson('/api/classify', [
        'text' => longJobDescription(),
        'url' => $url,
    ])->assertStatus(502);

    expect(Cache::has('classification:url:'.sha1($url)))->toBeFalse();
});

it('rate limits requests beyond 10 per minute per ip', function () {
    $this->mock(ClassificationService::class, function ($mock) {
        $mock->shouldReceive('classify')
            ->andReturn([
                'verdict' => 'WORLDWIDE',
                'confidence' => 'HIGH',
                'reason' => 'Truly remote.',
                'signals' => ['work from anywhere'],
            ]);
    });

    $payload = ['text' => longJobDescription()];

    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/classify', $payload)->assertOk();
    }

    $response = $this->postJson('/api/classify', $payload);

    $response
        ->assertStatus(429)
        ->assertHeader('X-RateLimit-Limit', '10')
        ->assertHeader('X-RateLimit-Remaining', '0');
});

it('rate limits per anon-id, so rotating IPs does not unlock more throughput', function () {
    $this->mock(ClassificationService::class, function ($mock) {
        $mock->shouldReceive('classify')
            ->andReturn([
                'verdict' => 'WORLDWIDE',
                'confidence' => 'HIGH',
                'reason' => 'Truly remote.',
                'signals' => [],
            ]);
    });

    $payload = ['text' => longJobDescription()];

    for ($i = 0; $i < 10; $i++) {
        $this->withHeader('X-Anon-Id', 'shared-anon')
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.$i])
            ->postJson('/api/classify', $payload)
            ->assertOk();
    }

    $this->withHeader('X-Anon-Id', 'shared-anon')
        ->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
        ->postJson('/api/classify', $payload)
        ->assertStatus(429);
});

it('returns 429 when the daily anthropic cap is reached', function () {
    config()->set('services.anthropic.daily_cap', 3);

    Cache::put('anthropic:spend:'.now()->format('Y-m-d'), 3, now()->endOfDay());

    $mock = $this->mock(ClassificationService::class);
    $mock->shouldNotReceive('classify');

    $response = $this->postJson('/api/classify', [
        'text' => longJobDescription(),
    ]);

    $response
        ->assertStatus(429)
        ->assertExactJson([
            'error' => 'Daily classification cap reached. Try again tomorrow.',
        ]);
});

it('returns 429 when the per-IP daily cap is reached', function () {
    config()->set('services.anthropic.per_ip_daily_cap', 2);

    Cache::put('anthropic:spend:ip:127.0.0.1:'.now()->format('Y-m-d'), 2, now()->endOfDay());

    $mock = $this->mock(ClassificationService::class);
    $mock->shouldNotReceive('classify');

    $response = $this->postJson('/api/classify', [
        'text' => longJobDescription(),
    ]);

    $response
        ->assertStatus(429)
        ->assertExactJson([
            'error' => 'Daily classification cap reached for your network. Try again tomorrow.',
        ]);
});

it('keeps per-IP daily counters separate across different IPs', function () {
    config()->set('services.anthropic.per_ip_daily_cap', 1);

    Cache::put('anthropic:spend:ip:10.0.0.1:'.now()->format('Y-m-d'), 1, now()->endOfDay());

    $this->mock(ClassificationService::class, function ($mock) {
        $mock->shouldReceive('classify')->once()->andReturn([
            'verdict' => 'WORLDWIDE',
            'confidence' => 'HIGH',
            'reason' => 'Truly remote.',
            'signals' => [],
        ]);
    });

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->postJson('/api/classify', ['text' => longJobDescription()])
        ->assertOk();
});

it('increments the daily counter on a live classification', function () {
    config()->set('services.anthropic.daily_cap', 100);

    $this->mock(ClassificationService::class, function ($mock) {
        $mock->shouldReceive('classify')
            ->twice()
            ->andReturn([
                'verdict' => 'WORLDWIDE',
                'confidence' => 'HIGH',
                'reason' => 'Truly remote.',
                'signals' => [],
            ]);
    });

    $this->postJson('/api/classify', ['text' => longJobDescription()])->assertOk();
    $this->postJson('/api/classify', ['text' => longJobDescription()])->assertOk();

    expect((int) Cache::get('anthropic:spend:'.now()->format('Y-m-d')))->toBe(2);
});

it('does not increment the counter on a cache hit', function () {
    config()->set('services.anthropic.daily_cap', 100);

    $url = 'https://example.com/jobs/cached';

    Cache::put('classification:url:'.sha1($url), [
        'verdict' => 'RESTRICTED',
        'confidence' => 'HIGH',
        'reason' => 'US only.',
        'signals' => [],
    ], now()->addHours(24));

    $mock = $this->mock(ClassificationService::class);
    $mock->shouldNotReceive('classify');

    $this->postJson('/api/classify', [
        'text' => longJobDescription(),
        'url' => $url,
    ])->assertOk()->assertJsonPath('cached', true);

    expect(Cache::has('anthropic:spend:'.now()->format('Y-m-d')))->toBeFalse();
});

it('still serves cache hits when the daily cap is reached', function () {
    config()->set('services.anthropic.daily_cap', 1);

    $url = 'https://example.com/jobs/cached';

    Cache::put('classification:url:'.sha1($url), [
        'verdict' => 'WORLDWIDE',
        'confidence' => 'HIGH',
        'reason' => 'Truly remote.',
        'signals' => [],
    ], now()->addHours(24));
    Cache::put('anthropic:spend:'.now()->format('Y-m-d'), 999, now()->endOfDay());

    $mock = $this->mock(ClassificationService::class);
    $mock->shouldNotReceive('classify');

    $this->postJson('/api/classify', [
        'text' => longJobDescription(),
        'url' => $url,
    ])->assertOk()->assertJsonPath('cached', true);
});

it('returns 429 when the per-anon monthly cap is reached', function () {
    config()->set('services.anthropic.per_anon_monthly_cap', 2);

    $month = now()->format('Y-m');
    Cache::put('anthropic:spend:anon:user-abc:'.$month, 2, now()->endOfMonth());

    $mock = $this->mock(ClassificationService::class);
    $mock->shouldNotReceive('classify');

    $response = $this->withHeader('X-Anon-Id', 'user-abc')
        ->postJson('/api/classify', ['text' => longJobDescription()]);

    $response
        ->assertStatus(429)
        ->assertJsonPath('error', "You've reached your monthly classification limit. Quota resets at the start of next month.")
        ->assertJsonPath('waitlist.available', true)
        ->assertJsonPath('waitlist.plan_interest', 'pro')
        ->assertJsonPath('waitlist.source', 'quota_429');
});

it('omits the waitlist CTA from the daily cap 429', function () {
    config()->set('services.anthropic.daily_cap', 1);

    Cache::put('anthropic:spend:'.now()->format('Y-m-d'), 1, now()->endOfDay());

    $mock = $this->mock(ClassificationService::class);
    $mock->shouldNotReceive('classify');

    $response = $this->postJson('/api/classify', ['text' => longJobDescription()]);

    $response
        ->assertStatus(429)
        ->assertJsonMissingPath('waitlist');
});

it('omits the waitlist CTA from the global monthly cap 429', function () {
    config()->set('services.anthropic.monthly_cap', 1);

    $month = now()->format('Y-m');
    Cache::put('anthropic:spend:month:'.$month, 1, now()->endOfMonth());

    $mock = $this->mock(ClassificationService::class);
    $mock->shouldNotReceive('classify');

    $response = $this->postJson('/api/classify', ['text' => longJobDescription()]);

    $response
        ->assertStatus(429)
        ->assertJsonMissingPath('waitlist');
});

it('returns 429 when the global monthly cap is reached', function () {
    config()->set('services.anthropic.monthly_cap', 5);

    $month = now()->format('Y-m');
    Cache::put('anthropic:spend:month:'.$month, 5, now()->endOfMonth());

    $mock = $this->mock(ClassificationService::class);
    $mock->shouldNotReceive('classify');

    $response = $this->postJson('/api/classify', ['text' => longJobDescription()]);

    $response
        ->assertStatus(429)
        ->assertJsonPath('error', 'Monthly service limit reached. Try again next month.');
});

it('increments per-anon, monthly, and daily counters together on a successful call', function () {
    $this->mock(ClassificationService::class, function ($mock) {
        $mock->shouldReceive('classify')->once()->andReturn([
            'verdict' => 'WORLDWIDE',
            'confidence' => 'HIGH',
            'reason' => 'Truly remote.',
            'signals' => [],
        ]);
    });

    $this->withHeader('X-Anon-Id', 'user-xyz')
        ->postJson('/api/classify', ['text' => longJobDescription()])
        ->assertOk();

    $month = now()->format('Y-m');
    $today = now()->format('Y-m-d');

    expect((int) Cache::get('anthropic:spend:anon:user-xyz:'.$month))->toBe(1)
        ->and((int) Cache::get('anthropic:spend:month:'.$month))->toBe(1)
        ->and((int) Cache::get('anthropic:spend:'.$today))->toBe(1)
        ->and((int) Cache::get('anthropic:spend:ip:127.0.0.1:'.$today))->toBe(1);
});

it('keeps per-anon counters separate across different anon IDs', function () {
    $this->mock(ClassificationService::class, function ($mock) {
        $mock->shouldReceive('classify')->twice()->andReturn([
            'verdict' => 'WORLDWIDE',
            'confidence' => 'HIGH',
            'reason' => 'Truly remote.',
            'signals' => [],
        ]);
    });

    $this->withHeader('X-Anon-Id', 'user-a')
        ->postJson('/api/classify', ['text' => longJobDescription()])
        ->assertOk();

    $this->withHeader('X-Anon-Id', 'user-b')
        ->postJson('/api/classify', ['text' => longJobDescription()])
        ->assertOk();

    $month = now()->format('Y-m');

    expect((int) Cache::get('anthropic:spend:anon:user-a:'.$month))->toBe(1)
        ->and((int) Cache::get('anthropic:spend:anon:user-b:'.$month))->toBe(1)
        ->and((int) Cache::get('anthropic:spend:month:'.$month))->toBe(2);
});

it('does not increment any counter when classification fails', function () {
    config()->set('services.anthropic.daily_cap', 100);
    Log::spy();

    $this->mock(ClassificationService::class, function ($mock) {
        $mock->shouldReceive('classify')
            ->once()
            ->andThrow(new RuntimeException('boom'));
    });

    $this->postJson('/api/classify', ['text' => longJobDescription()])->assertStatus(502);

    expect(Cache::has('anthropic:spend:'.now()->format('Y-m-d')))->toBeFalse();
});

it('records a classify.completed event for a live classification', function () {
    Http::fake([
        'https://api.anthropic.test/*' => Http::response(fakeAnthropicHttpResponse([
            'verdict' => 'WORLDWIDE',
            'confidence' => 'HIGH',
            'reason' => 'Hiring globally.',
            'signals' => ['work from anywhere'],
        ])),
    ]);

    $this->withHeader('X-Anon-Id', 'browser-uuid-123')
        ->postJson('/api/classify', [
            'text' => longJobDescription(),
            'url' => 'https://linkedin.com/jobs/view/42',
        ])
        ->assertOk();

    $event = Event::firstOrFail();

    expect($event->anon_id)->toBe('browser-uuid-123')
        ->and($event->name)->toBe('classify.completed')
        ->and($event->host)->toBe('linkedin.com')
        ->and($event->verdict)->toBe('WORLDWIDE')
        ->and($event->cached)->toBeFalse()
        ->and($event->latency_ms)->toBeGreaterThanOrEqual(0);
});

it('records a classify.completed event with cached=true on cache hits', function () {
    $url = 'https://example.com/jobs/cached';

    Cache::put('classification:url:'.sha1($url), [
        'verdict' => 'RESTRICTED',
        'confidence' => 'HIGH',
        'reason' => 'US only.',
        'signals' => [],
    ], now()->addHours(24));

    $this->withHeader('X-Anon-Id', 'browser-uuid-456')
        ->postJson('/api/classify', [
            'text' => longJobDescription(),
            'url' => $url,
        ])
        ->assertOk()
        ->assertJsonPath('cached', true);

    $event = Event::firstOrFail();

    expect($event->anon_id)->toBe('browser-uuid-456')
        ->and($event->host)->toBe('example.com')
        ->and($event->verdict)->toBe('RESTRICTED')
        ->and($event->cached)->toBeTrue();
});

it('captures token usage and model from the Anthropic response', function () {
    Http::fake([
        'https://api.anthropic.test/*' => Http::response([
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-sonnet-4-20250514',
            'content' => [
                ['type' => 'text', 'text' => json_encode([
                    'verdict' => 'WORLDWIDE',
                    'confidence' => 'HIGH',
                    'reason' => 'Hiring globally.',
                    'signals' => [],
                ], JSON_THROW_ON_ERROR)],
            ],
            'stop_reason' => 'end_turn',
            'usage' => [
                'input_tokens' => 412,
                'output_tokens' => 87,
                'cache_read_input_tokens' => 0,
                'cache_creation_input_tokens' => 0,
            ],
        ]),
    ]);

    $this->postJson('/api/classify', ['text' => longJobDescription()])->assertOk();

    $event = Event::firstOrFail();

    expect($event->model)->toBe('claude-sonnet-4-20250514')
        ->and($event->input_tokens)->toBe(412)
        ->and($event->output_tokens)->toBe(87)
        ->and($event->cache_read_input_tokens)->toBe(0)
        ->and($event->cache_creation_input_tokens)->toBe(0);
});

it('records null usage when the Anthropic payload lacks a usage block', function () {
    Http::fake([
        'https://api.anthropic.test/*' => Http::response([
            'id' => 'msg_test',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-sonnet-4-20250514',
            'content' => [
                ['type' => 'text', 'text' => json_encode([
                    'verdict' => 'WORLDWIDE',
                    'confidence' => 'HIGH',
                    'reason' => 'Hiring globally.',
                    'signals' => [],
                ], JSON_THROW_ON_ERROR)],
            ],
        ]),
    ]);

    $this->postJson('/api/classify', ['text' => longJobDescription()])->assertOk();

    $event = Event::firstOrFail();

    expect($event->model)->toBe('claude-sonnet-4-20250514')
        ->and($event->input_tokens)->toBeNull()
        ->and($event->output_tokens)->toBeNull()
        ->and($event->cache_read_input_tokens)->toBeNull()
        ->and($event->cache_creation_input_tokens)->toBeNull();
});

it('stores null usage on cache hits', function () {
    $url = 'https://example.com/jobs/cached-tokens';

    Cache::put('classification:url:'.sha1($url), [
        'verdict' => 'RESTRICTED',
        'confidence' => 'HIGH',
        'reason' => 'US only.',
        'signals' => [],
    ], now()->addHours(24));

    $this->postJson('/api/classify', [
        'text' => longJobDescription(),
        'url' => $url,
    ])->assertOk()->assertJsonPath('cached', true);

    $event = Event::firstOrFail();

    expect($event->cached)->toBeTrue()
        ->and($event->model)->toBeNull()
        ->and($event->input_tokens)->toBeNull()
        ->and($event->output_tokens)->toBeNull()
        ->and($event->cache_read_input_tokens)->toBeNull()
        ->and($event->cache_creation_input_tokens)->toBeNull();
});

it('falls back to "unknown" anon_id when X-Anon-Id header is missing', function () {
    Http::fake([
        'https://api.anthropic.test/*' => Http::response(fakeAnthropicHttpResponse([
            'verdict' => 'UNCLEAR',
            'confidence' => 'MEDIUM',
            'reason' => 'No context.',
            'signals' => [],
        ])),
    ]);

    $this->postJson('/api/classify', ['text' => longJobDescription()])->assertOk();

    expect(Event::firstOrFail()->anon_id)->toBe('unknown');
});

it('does not store the host when no url is provided', function () {
    Http::fake([
        'https://api.anthropic.test/*' => Http::response(fakeAnthropicHttpResponse([
            'verdict' => 'WORLDWIDE',
            'confidence' => 'HIGH',
            'reason' => 'Truly remote.',
            'signals' => [],
        ])),
    ]);

    $this->postJson('/api/classify', ['text' => longJobDescription()])->assertOk();

    expect(Event::firstOrFail()->host)->toBeNull();
});

it('does not record an event when validation fails', function () {
    $this->postJson('/api/classify', ['text' => 'short'])->assertStatus(422);

    expect(Event::count())->toBe(0);
});

it('does not record an event when classification throws', function () {
    Log::spy();

    $this->mock(ClassificationService::class, function ($mock) {
        $mock->shouldReceive('classify')->once()->andThrow(new RuntimeException('boom'));
    });

    $this->postJson('/api/classify', ['text' => longJobDescription()])->assertStatus(502);

    expect(Event::count())->toBe(0);
});

it('truncates oversized X-Anon-Id values to 64 characters', function () {
    Http::fake([
        'https://api.anthropic.test/*' => Http::response(fakeAnthropicHttpResponse([
            'verdict' => 'WORLDWIDE',
            'confidence' => 'HIGH',
            'reason' => 'Truly remote.',
            'signals' => [],
        ])),
    ]);

    $this->withHeader('X-Anon-Id', str_repeat('a', 200))
        ->postJson('/api/classify', ['text' => longJobDescription()])
        ->assertOk();

    expect(Event::firstOrFail()->anon_id)->toBe(str_repeat('a', 64));
});

it('still returns a successful response when event recording fails', function () {
    Log::spy();

    Http::fake([
        'https://api.anthropic.test/*' => Http::response(fakeAnthropicHttpResponse([
            'verdict' => 'WORLDWIDE',
            'confidence' => 'HIGH',
            'reason' => 'Truly remote.',
            'signals' => [],
        ])),
    ]);

    Schema::drop('events');

    $this->postJson('/api/classify', ['text' => longJobDescription()])
        ->assertOk()
        ->assertJsonPath('verdict', 'WORLDWIDE');

    Log::shouldHaveReceived('warning')->once();
});

it('exposes rate limit headers on successful responses', function () {
    $this->mock(ClassificationService::class, function ($mock) {
        $mock->shouldReceive('classify')
            ->once()
            ->andReturn([
                'verdict' => 'WORLDWIDE',
                'confidence' => 'HIGH',
                'reason' => 'Truly remote.',
                'signals' => ['work from anywhere'],
            ]);
    });

    $response = $this->postJson('/api/classify', [
        'text' => longJobDescription(),
    ]);

    $response
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', '10')
        ->assertHeader('X-RateLimit-Remaining', '9');
});
