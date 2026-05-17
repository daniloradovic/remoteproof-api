<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaitlistSignup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WaitlistController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email:rfc', 'max:255'],
            'plan_interest' => ['nullable', 'in:pro'],
            'source' => ['nullable', 'string', 'max:64'],
        ], [
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.max' => 'Email is too long.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => $validator->errors()->first(),
            ], 422);
        }

        $validated = $validator->validated();
        $email = strtolower(trim($validated['email']));

        $alreadySignedUp = WaitlistSignup::where('email', $email)->exists();

        $anonId = $request->header('X-Anon-Id');
        $anonId = $anonId !== null ? substr((string) $anonId, 0, 64) : null;

        $referer = $request->header('Referer');
        $hostReferrer = null;
        if (is_string($referer) && $referer !== '') {
            $hostReferrer = parse_url($referer, PHP_URL_HOST) ?: null;
        }

        $userAgent = $request->userAgent();
        if (is_string($userAgent) && strlen($userAgent) > 512) {
            $userAgent = substr($userAgent, 0, 512);
        }

        WaitlistSignup::create([
            'email' => $email,
            'plan_interest' => $validated['plan_interest'] ?? null,
            'anon_id' => $anonId,
            'host_referrer' => $hostReferrer,
            'source' => $validated['source'] ?? null,
            'user_agent' => $userAgent,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'ok' => true,
            'already_signed_up' => $alreadySignedUp,
        ]);
    }
}
