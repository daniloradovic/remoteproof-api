<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ClassificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class ClassifyController extends Controller
{
    private const CACHE_TTL_HOURS = 24;

    private const CACHE_KEY_PREFIX = 'classification:url:';

    public function __construct(private readonly ClassificationService $classifier) {}

    public function classify(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'text' => ['required', 'string', 'min:100'],
            'url' => ['nullable', 'url'],
        ], [
            'text.required' => 'The text field is required and must be at least 100 characters.',
            'text.string' => 'The text field is required and must be at least 100 characters.',
            'text.min' => 'The text field is required and must be at least 100 characters.',
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

        if ($cacheKey !== null) {
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                return response()->json([
                    'verdict' => $cached['verdict'],
                    'confidence' => $cached['confidence'],
                    'reason' => $cached['reason'],
                    'signals' => $cached['signals'],
                    'cached' => true,
                ]);
            }
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

        if ($cacheKey !== null) {
            Cache::put($cacheKey, $result, now()->addHours(self::CACHE_TTL_HOURS));
        }

        return response()->json([
            'verdict' => $result['verdict'],
            'confidence' => $result['confidence'],
            'reason' => $result['reason'],
            'signals' => $result['signals'],
            'cached' => false,
        ]);
    }
}
