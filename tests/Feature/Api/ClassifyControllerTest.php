<?php

declare(strict_types=1);

use App\Services\ClassificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config()->set('services.anthropic.key', 'test-key');
    config()->set('services.anthropic.url', 'https://api.anthropic.test/v1/messages');
    config()->set('services.anthropic.model', 'claude-sonnet-4-20250514');
    config()->set('services.anthropic.max_tokens', 1024);
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

it('always reports cached as false in this stage', function () {
    Http::fake([
        'https://api.anthropic.test/*' => Http::response(fakeAnthropicHttpResponse([
            'verdict' => 'WORLDWIDE',
            'confidence' => 'HIGH',
            'reason' => 'Truly remote.',
            'signals' => ['work from anywhere'],
        ])),
    ]);

    $response = $this->postJson('/api/classify', [
        'text' => longJobDescription(),
        'url' => 'https://example.com/jobs/abc',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('cached', false);
});
