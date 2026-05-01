<?php

declare(strict_types=1);

use App\Services\ClassificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config()->set('services.anthropic.key', 'test-key');
    config()->set('services.anthropic.url', 'https://api.anthropic.test/v1/messages');
    config()->set('services.anthropic.model', 'claude-sonnet-4-20250514');
    config()->set('services.anthropic.max_tokens', 1024);

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
