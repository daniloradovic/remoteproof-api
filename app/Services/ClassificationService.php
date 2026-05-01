<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ClassificationService
{
    private const ALLOWED_VERDICTS = ['WORLDWIDE', 'RESTRICTED', 'UNCLEAR'];

    private const ALLOWED_CONFIDENCE = ['HIGH', 'MEDIUM', 'LOW'];

    public function __construct(private readonly HttpFactory $http) {}

    /**
     * Classify a job description by calling the Anthropic API.
     *
     * @return array{verdict: string, confidence: string, reason: string, signals: array<int, string>}
     *
     * @throws RuntimeException when the API call fails or the response cannot be parsed.
     */
    public function classify(string $jobDescription): array
    {
        $jobDescription = trim($jobDescription);

        if ($jobDescription === '') {
            throw new RuntimeException('Job description cannot be empty.');
        }

        $apiKey = config('services.anthropic.key');
        $apiUrl = config('services.anthropic.url');
        $model = config('services.anthropic.model');
        $maxTokens = (int) config('services.anthropic.max_tokens', 1024);

        if (! $apiKey || ! $apiUrl || ! $model) {
            throw new RuntimeException('Anthropic API is not configured.');
        }

        $prompt = $this->buildPrompt($jobDescription);

        try {
            $response = $this->http
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->timeout(30)
                ->post($apiUrl, [
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ]);
        } catch (ConnectionException $e) {
            Log::error('Anthropic API connection failure', ['error' => $e->getMessage()]);
            throw new RuntimeException('Failed to reach Anthropic API: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            Log::error('Anthropic API returned an error response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            try {
                $response->throw();
            } catch (RequestException $e) {
                throw new RuntimeException(
                    'Anthropic API request failed with status '.$response->status(),
                    previous: $e,
                );
            }
        }

        $payload = $response->json();
        $rawText = $this->extractText($payload);

        return $this->parseClassification($rawText);
    }

    /**
     * Build the classification prompt from the job description.
     */
    public function buildPrompt(string $jobDescription): string
    {
        return <<<PROMPT
You are an expert at analyzing job descriptions to determine whether a
remote position is genuinely open to candidates worldwide, or restricted
to specific countries or regions.

Analyze the job description below and classify it as one of:
- WORLDWIDE: Truly remote, no geographic restrictions mentioned or implied
- RESTRICTED: Remote but limited to specific countries, regions, timezones,
  or requires specific work authorization
- UNCLEAR: Remote is mentioned but geographic scope is ambiguous

RESTRICTED signals to look for:
- "Must be authorized to work in [country]"
- "US persons only", "US citizens", "EEA only"
- Payroll/tax mentions tied to a specific country
- "Must reside in [country/state]"
- Timezone requirements that only cover one continent (e.g. "EST/CST hours only")
- Benefits tied to a specific country's healthcare or legal system

WORLDWIDE signals to look for:
- "Work from anywhere"
- "No timezone restrictions"
- Explicitly hiring across multiple continents
- Global payroll providers mentioned (Deel, Remote.com, Rippling)

UNCLEAR signals:
- "Remote" with zero geographic context
- Vague timezone overlap ("overlap with US hours preferred")

Respond in valid JSON only. No explanation outside the JSON block.
{
  "verdict": "WORLDWIDE|RESTRICTED|UNCLEAR",
  "confidence": "HIGH|MEDIUM|LOW",
  "reason": "One sentence explanation of the verdict",
  "signals": ["list", "of", "detected", "signals"]
}

Job description:
"""
{$jobDescription}
"""
PROMPT;
    }

    /**
     * Extract the assistant's textual response from the Anthropic payload.
     */
    private function extractText(mixed $payload): string
    {
        if (! is_array($payload) || ! isset($payload['content']) || ! is_array($payload['content'])) {
            throw new RuntimeException('Anthropic API returned an unexpected response shape.');
        }

        $text = '';
        foreach ($payload['content'] as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text' && isset($block['text'])) {
                $text .= $block['text'];
            }
        }

        $text = trim($text);

        if ($text === '') {
            throw new RuntimeException('Anthropic API returned an empty text response.');
        }

        return $text;
    }

    /**
     * Parse and validate the JSON classification block returned by Claude.
     *
     * @return array{verdict: string, confidence: string, reason: string, signals: array<int, string>}
     */
    private function parseClassification(string $rawText): array
    {
        $json = $this->isolateJson($rawText);

        $decoded = json_decode($json, associative: true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Failed to decode classification JSON: '.json_last_error_msg());
        }

        foreach (['verdict', 'confidence', 'reason', 'signals'] as $key) {
            if (! array_key_exists($key, $decoded)) {
                throw new RuntimeException("Classification JSON missing required key: {$key}");
            }
        }

        $verdict = strtoupper((string) $decoded['verdict']);
        $confidence = strtoupper((string) $decoded['confidence']);
        $reason = trim((string) $decoded['reason']);
        $signals = $decoded['signals'];

        if (! in_array($verdict, self::ALLOWED_VERDICTS, true)) {
            throw new RuntimeException("Invalid verdict value: {$verdict}");
        }

        if (! in_array($confidence, self::ALLOWED_CONFIDENCE, true)) {
            throw new RuntimeException("Invalid confidence value: {$confidence}");
        }

        if (! is_array($signals)) {
            throw new RuntimeException('Signals must be a list of strings.');
        }

        $normalisedSignals = [];
        foreach ($signals as $signal) {
            if (! is_string($signal) && ! is_numeric($signal)) {
                throw new RuntimeException('Signals must contain only strings.');
            }
            $normalisedSignals[] = (string) $signal;
        }

        return [
            'verdict' => $verdict,
            'confidence' => $confidence,
            'reason' => $reason,
            'signals' => $normalisedSignals,
        ];
    }

    /**
     * Pull the JSON object out of Claude's response, even if surrounded by stray text.
     */
    private function isolateJson(string $text): string
    {
        $trimmed = trim($text);

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/```\s*$/', '', $trimmed) ?? $trimmed;
            $trimmed = trim($trimmed);
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');

        if ($start === false || $end === false || $end <= $start) {
            throw new RuntimeException('Could not locate JSON object in API response.');
        }

        return substr($trimmed, $start, $end - $start + 1);
    }
}
