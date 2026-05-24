<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\ClassificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RuntimeException;
use Throwable;

class ClassifyController extends Controller
{
    private const CACHE_TTL_HOURS = 24;

    public const CACHE_KEY_PREFIX = 'classification:url:';

    public const DAILY_SPEND_PREFIX = 'anthropic:spend:';

    public const MONTHLY_SPEND_PREFIX = 'anthropic:spend:month:';

    public const ANON_MONTHLY_SPEND_PREFIX = 'anthropic:spend:anon:';

    public const IP_DAILY_SPEND_PREFIX = 'anthropic:spend:ip:';

    public function __construct(private readonly ClassificationService $classifier) {}

    public function classify(Request $request): JsonResponse
    {
        $start = microtime(true);

        $validator = Validator::make($request->all(), [
            'text' => ['required', 'string', 'min:100', 'max:16384'],
            'url' => ['nullable', 'url'],
        ], [
            'text.required' => 'The text field is required and must be at least 100 characters.',
            'text.string' => 'The text field is required and must be at least 100 characters.',
            'text.min' => 'The text field is required and must be at least 100 characters.',
            'text.max' => 'The text field must not exceed 16384 characters.',
            'url.url' => 'The url field must be a valid URL.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first(),
            ], 422);
        }

        $validated = $validator->validated();
        $url = $validated['url'] ?? null;
        $cacheKey = $url !== null ? self::CACHE_KEY_PREFIX.sha1($url) : null;
        $host = $url !== null ? (parse_url($url, PHP_URL_HOST) ?: null) : null;
        $anonId = substr((string) $request->header('X-Anon-Id', 'unknown'), 0, 64);

        if ($cacheKey !== null) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                $this->recordClassification($anonId, $host, $cached['verdict'], true, $start, null);

                return response()->json([
                    'verdict' => $cached['verdict'],
                    'confidence' => $cached['confidence'],
                    'reason' => $cached['reason'],
                    'signals' => $cached['signals'],
                    'cached' => true,
                ]);
            }
        }

        $month = now()->format('Y-m');
        $today = now()->format('Y-m-d');

        $ip = $request->ip() ?? 'no-ip';

        $anonKey = self::ANON_MONTHLY_SPEND_PREFIX.$anonId.':'.$month;
        $monthlyKey = self::MONTHLY_SPEND_PREFIX.$month;
        $dailyKey = self::DAILY_SPEND_PREFIX.$today;
        $ipDailyKey = self::IP_DAILY_SPEND_PREFIX.$ip.':'.$today;

        $anonCap = (int) config('services.anthropic.per_anon_monthly_cap', 50);
        $monthlyCap = (int) config('services.anthropic.monthly_cap', 5000);
        $dailyCap = (int) config('services.anthropic.daily_cap', 250);
        $ipDailyCap = (int) config('services.anthropic.per_ip_daily_cap', 50);

        if ($this->exceeded($anonKey, $anonCap)) {
            Log::info('classify cap hit', ['which' => 'anon', 'anon_id' => $anonId, 'count' => (int) Cache::get($anonKey, 0), 'cap' => $anonCap]);

            return response()->json([
                'error' => "You've reached your monthly classification limit. Quota resets at the start of next month.",
                'waitlist' => [
                    'available' => true,
                    'plan_interest' => 'pro',
                    'source' => 'quota_429',
                ],
            ], 429);
        }

        if ($this->exceeded($ipDailyKey, $ipDailyCap)) {
            Log::info('classify cap hit', ['which' => 'ip_daily', 'ip' => $ip, 'count' => (int) Cache::get($ipDailyKey, 0), 'cap' => $ipDailyCap]);

            return response()->json([
                'error' => 'Daily classification cap reached for your network. Try again tomorrow.',
            ], 429);
        }

        if ($this->exceeded($monthlyKey, $monthlyCap)) {
            Log::info('classify cap hit', ['which' => 'monthly', 'count' => (int) Cache::get($monthlyKey, 0), 'cap' => $monthlyCap]);

            return response()->json([
                'error' => 'Monthly service limit reached. Try again next month.',
            ], 429);
        }

        if ($this->exceeded($dailyKey, $dailyCap)) {
            Log::info('classify cap hit', ['which' => 'daily', 'count' => (int) Cache::get($dailyKey, 0), 'cap' => $dailyCap]);

            return response()->json([
                'error' => 'Daily classification cap reached. Try again tomorrow.',
            ], 429);
        }

        try {
            $result = $this->classifier->classify($validated['text']);
        } catch (RuntimeException $e) {
            Log::error('Classification failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to classify the job description.',
            ], 502);
        }

        $this->bump($anonKey, now()->endOfMonth());
        $this->bump($monthlyKey, now()->endOfMonth());
        $this->bump($dailyKey, now()->endOfDay());
        $this->bump($ipDailyKey, now()->endOfDay());

        if ($cacheKey !== null) {
            Cache::put($cacheKey, $result, now()->addHours(self::CACHE_TTL_HOURS));
        }

        $this->recordClassification($anonId, $host, $result['verdict'], false, $start, $result['usage'] ?? null);

        return response()->json([
            'verdict' => $result['verdict'],
            'confidence' => $result['confidence'],
            'reason' => $result['reason'],
            'signals' => $result['signals'],
            'cached' => false,
        ]);
    }

    private function exceeded(string $key, int $cap): bool
    {
        return (int) Cache::get($key, 0) >= $cap;
    }

    private function bump(string $key, \DateTimeInterface $expiresAt): void
    {
        Cache::add($key, 0, $expiresAt);
        Cache::increment($key);
    }

    private function recordClassification(string $anonId, ?string $host, string $verdict, bool $cached, float $start, ?array $usage): void
    {
        $usage ??= [];

        try {
            Event::create([
                'anon_id' => $anonId,
                'name' => 'classify.completed',
                'host' => $host,
                'verdict' => $verdict,
                'model' => $usage['model'] ?? null,
                'input_tokens' => $usage['input_tokens'] ?? null,
                'output_tokens' => $usage['output_tokens'] ?? null,
                'cache_read_input_tokens' => $usage['cache_read_input_tokens'] ?? null,
                'cache_creation_input_tokens' => $usage['cache_creation_input_tokens'] ?? null,
                'cached' => $cached,
                'latency_ms' => (int) ((microtime(true) - $start) * 1000),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to record classification event', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
