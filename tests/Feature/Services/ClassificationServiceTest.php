<?php

declare(strict_types=1);

use App\Services\ClassificationService;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.anthropic.key', 'test-key');
    config()->set('services.anthropic.url', 'https://api.anthropic.test/v1/messages');
    config()->set('services.anthropic.model', 'claude-sonnet-4-20250514');
    config()->set('services.anthropic.max_tokens', 1024);
});

function fakeAnthropicResponse(array $payload): array
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

function makeService(): ClassificationService
{
    return new ClassificationService(app(HttpFactory::class));
}

it('returns a structured classification array on success', function () {
    Http::fake([
        'https://api.anthropic.test/*' => Http::response(fakeAnthropicResponse([
            'verdict' => 'WORLDWIDE',
            'confidence' => 'HIGH',
            'reason' => 'No geographic restrictions and Deel is named as the payroll provider.',
            'signals' => ['work from anywhere', 'Deel mentioned'],
        ])),
    ]);

    $result = makeService()->classify(str_repeat('Remote position open to anyone. ', 10));

    expect($result)
        ->toMatchArray([
            'verdict' => 'WORLDWIDE',
            'confidence' => 'HIGH',
        ])
        ->and($result['signals'])->toBe(['work from anywhere', 'Deel mentioned'])
        ->and($result['reason'])->toContain('Deel');
});

it('sends the configured headers and request body to Anthropic', function () {
    Http::fake([
        'https://api.anthropic.test/*' => Http::response(fakeAnthropicResponse([
            'verdict' => 'RESTRICTED',
            'confidence' => 'MEDIUM',
            'reason' => 'US authorisation required.',
            'signals' => ['Must be authorized to work in the US'],
        ])),
    ]);

    makeService()->classify('Senior engineer. Must be authorized to work in the US.');

    Http::assertSent(function ($request) {
        expect($request->url())->toBe('https://api.anthropic.test/v1/messages');
        expect($request->method())->toBe('POST');
        expect($request->header('x-api-key'))->toBe(['test-key']);
        expect($request->header('anthropic-version'))->toBe(['2023-06-01']);

        $body = $request->data();

        expect($body['model'] ?? null)->toBe('claude-sonnet-4-20250514');
        expect($body['max_tokens'] ?? null)->toBe(1024);
        expect($body['messages'][0]['role'] ?? null)->toBe('user');
        expect($body['messages'][0]['content'] ?? '')
            ->toContain('Must be authorized to work in the US');

        return true;
    });
});

it('uppercases verdict and confidence and coerces signals to strings', function () {
    Http::fake([
        'https://api.anthropic.test/*' => Http::response(fakeAnthropicResponse([
            'verdict' => 'restricted',
            'confidence' => 'low',
            'reason' => 'Possibly EU only.',
            'signals' => ['EU only', 42],
        ])),
    ]);

    $result = makeService()->classify('Some long enough job description here.');

    expect($result['verdict'])->toBe('RESTRICTED')
        ->and($result['confidence'])->toBe('LOW')
        ->and($result['signals'])->toBe(['EU only', '42']);
});

it('extracts JSON when wrapped in a markdown code fence', function () {
    $jsonString = json_encode([
        'verdict' => 'UNCLEAR',
        'confidence' => 'MEDIUM',
        'reason' => 'Remote with no geographic context.',
        'signals' => ['remote with no context'],
    ], JSON_THROW_ON_ERROR);

    Http::fake([
        'https://api.anthropic.test/*' => Http::response([
            'content' => [
                ['type' => 'text', 'text' => "```json\n{$jsonString}\n```"],
            ],
        ]),
    ]);

    $result = makeService()->classify('A remote role. We hire remotely.');

    expect($result['verdict'])->toBe('UNCLEAR')
        ->and($result['signals'])->toBe(['remote with no context']);
});

it('extracts JSON when surrounded by extra prose', function () {
    $jsonString = json_encode([
        'verdict' => 'WORLDWIDE',
        'confidence' => 'HIGH',
        'reason' => 'Hiring across multiple continents.',
        'signals' => ['multiple continents'],
    ], JSON_THROW_ON_ERROR);

    Http::fake([
        'https://api.anthropic.test/*' => Http::response([
            'content' => [
                ['type' => 'text', 'text' => "Sure, here is the result: {$jsonString} Hope that helps!"],
            ],
        ]),
    ]);

    $result = makeService()->classify('Remote role hiring across multiple continents.');

    expect($result['verdict'])->toBe('WORLDWIDE');
});

it('throws when the API responds with a failure status', function () {
    Http::fake([
        'https://api.anthropic.test/*' => Http::response(['error' => 'overloaded'], 503),
    ]);

    expect(fn () => makeService()->classify('A long enough job description.'))
        ->toThrow(RuntimeException::class, 'Anthropic API request failed');
});

it('throws when the response body is not valid JSON', function () {
    Http::fake([
        'https://api.anthropic.test/*' => Http::response([
            'content' => [
                ['type' => 'text', 'text' => 'totally not json at all'],
            ],
        ]),
    ]);

    expect(fn () => makeService()->classify('A long enough job description.'))
        ->toThrow(RuntimeException::class, 'Could not locate JSON object');
});

it('throws when the verdict value is not allowed', function () {
    Http::fake([
        'https://api.anthropic.test/*' => Http::response(fakeAnthropicResponse([
            'verdict' => 'MAYBE',
            'confidence' => 'HIGH',
            'reason' => 'Bad verdict.',
            'signals' => [],
        ])),
    ]);

    expect(fn () => makeService()->classify('A long enough job description.'))
        ->toThrow(RuntimeException::class, 'Invalid verdict value: MAYBE');
});

it('throws when the confidence value is not allowed', function () {
    Http::fake([
        'https://api.anthropic.test/*' => Http::response(fakeAnthropicResponse([
            'verdict' => 'WORLDWIDE',
            'confidence' => 'CERTAIN',
            'reason' => 'Bad confidence.',
            'signals' => [],
        ])),
    ]);

    expect(fn () => makeService()->classify('A long enough job description.'))
        ->toThrow(RuntimeException::class, 'Invalid confidence value: CERTAIN');
});

it('throws when a required key is missing', function () {
    Http::fake([
        'https://api.anthropic.test/*' => Http::response(fakeAnthropicResponse([
            'verdict' => 'WORLDWIDE',
            'confidence' => 'HIGH',
            'reason' => 'Missing signals.',
        ])),
    ]);

    expect(fn () => makeService()->classify('A long enough job description.'))
        ->toThrow(RuntimeException::class, 'missing required key: signals');
});

it('throws when signals is not an array', function () {
    Http::fake([
        'https://api.anthropic.test/*' => Http::response(fakeAnthropicResponse([
            'verdict' => 'WORLDWIDE',
            'confidence' => 'HIGH',
            'reason' => 'Bad signals.',
            'signals' => 'should be a list',
        ])),
    ]);

    expect(fn () => makeService()->classify('A long enough job description.'))
        ->toThrow(RuntimeException::class, 'Signals must be a list of strings');
});

it('throws when the input is empty', function () {
    expect(fn () => makeService()->classify('   '))
        ->toThrow(RuntimeException::class, 'Job description cannot be empty');
});

it('throws when the API key is missing', function () {
    config()->set('services.anthropic.key', null);

    expect(fn () => makeService()->classify('A long enough job description.'))
        ->toThrow(RuntimeException::class, 'Anthropic API is not configured');
});

it('throws when the response shape is unexpected', function () {
    Http::fake([
        'https://api.anthropic.test/*' => Http::response(['nope' => true]),
    ]);

    expect(fn () => makeService()->classify('A long enough job description.'))
        ->toThrow(RuntimeException::class, 'unexpected response shape');
});

it('builds a prompt that contains both instructions and the job description', function () {
    $prompt = makeService()->buildPrompt('Hiring a backend engineer remotely.');

    expect($prompt)
        ->toContain('WORLDWIDE')
        ->toContain('RESTRICTED')
        ->toContain('UNCLEAR')
        ->toContain('Respond in valid JSON only')
        ->toContain('Hiring a backend engineer remotely.');
});
