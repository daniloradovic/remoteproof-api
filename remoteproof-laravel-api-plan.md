# RemoteProof — Laravel API Plan
> Backend classification API for remoteproof.app

---

## Stack

| Layer | Technology |
|---|---|
| Framework | Laravel 11 |
| Language | PHP 8.3 |
| AI | Claude API (claude-sonnet-4-20250514) |
| Database | SQLite (local) |
| Cache | File driver (local) |
| HTTP Client | Laravel built-in (Guzzle) |

---

## TASK 1 — Create Laravel project

```bash
composer create-project laravel/laravel remoteproof-api
cd remoteproof-api
php artisan serve
```

**Done when:** `http://localhost:8000` returns the Laravel welcome page.

---

## TASK 2 — Configure environment

Add to `.env`:
```
ANTHROPIC_API_KEY=your_key_here
ANTHROPIC_API_URL=https://api.anthropic.com/v1/messages
ANTHROPIC_MODEL=claude-sonnet-4-20250514
MAX_TOKENS=1024
```

Add to `config/services.php`:
```php
'anthropic' => [
    'key'   => env('ANTHROPIC_API_KEY'),
    'url'   => env('ANTHROPIC_API_URL'),
    'model' => env('ANTHROPIC_MODEL'),
    'max_tokens' => env('MAX_TOKENS', 1024),
],
```

Run:
```bash
php artisan config:clear
```

**Done when:** `config('services.anthropic.key')` returns your API key in tinker.

---

## TASK 3 — Create the Classification Service

Create `app/Services/ClassificationService.php`.

Responsibilities:
- Accept raw job description text as input
- Build the classification prompt
- Call Claude API via Laravel HTTP client
- Parse and validate the JSON response
- Return a structured array: `verdict`, `confidence`, `reason`, `signals`

Prompt to use inside the service:
```
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
```

**Done when:** Calling the service manually in `php artisan tinker` with a pasted
job description returns a correctly structured classification array.

---

## TASK 4 — Create the `/api/classify` endpoint

Create `app/Http/Controllers/Api/ClassifyController.php`.

- Method: `POST`
- Route: `POST /api/classify`
- Add to `routes/api.php`:
```php
Route::post('/classify', [ClassifyController::class, 'classify']);
```

Request body:
```json
{
  "text": "full job description text here",
  "url": "https://linkedin.com/jobs/view/123456 (optional)"
}
```

Validation rules:
- `text`: required, string, min:100 characters
- `url`: nullable, url

Response (success):
```json
{
  "verdict": "RESTRICTED",
  "confidence": "HIGH",
  "reason": "Requires US work authorization.",
  "signals": ["must be authorized to work in the US"],
  "cached": false
}
```

Response (validation error):
```json
{
  "error": "The text field is required and must be at least 100 characters."
}
```

**Done when:** A curl POST to `localhost:8000/api/classify` with a job description
returns a valid classification JSON response.

```bash
curl -X POST http://localhost:8000/api/classify \
  -H "Content-Type: application/json" \
  -d '{"text": "paste a job description here (min 100 chars)"}'
```

---

## TASK 5 — Add caching layer

- Cache classification results keyed by job URL (when provided)
- Driver: `file` (no Redis needed locally, set in `.env`: `CACHE_DRIVER=file`)
- TTL: 24 hours (`now()->addHours(24)`)
- If URL already cached, return result immediately with `"cached": true`
- If no URL provided, always call the API (can't cache anonymous text)

Logic inside `ClassifyController`:
```
if url provided:
    check cache for url
    if found: return cached result
call ClassificationService
if url provided: store result in cache
return result
```

**Done when:** Second POST request with the same URL returns instantly
and includes `"cached": true` in the response, with no API call made.

---

## TASK 6 — Add CORS middleware

The Chrome extension will call this API from a browser context.
Without CORS headers, every request will be blocked.

In `config/cors.php`, update:
```php
'paths' => ['api/*'],
'allowed_origins' => ['*'],  // tighten this to extension ID later
'allowed_methods' => ['POST', 'OPTIONS'],
'allowed_headers' => ['Content-Type', 'Accept'],
```

**Done when:** No CORS errors appear in Chrome DevTools console when the
extension calls the local API.

---

## TASK 7 — Prompt tuning with real job listings

Collect 10 real job descriptions and test each one:

| # | Source | Expected | Notes |
|---|---|---|---|
| 1 | LinkedIn | WORLDWIDE | Explicitly says "work from anywhere" |
| 2 | LinkedIn | RESTRICTED | US work auth required |
| 3 | Indeed | RESTRICTED | EU timezone only |
| 4 | WWR | WORLDWIDE | Deel mentioned as payroll |
| 5 | LinkedIn | UNCLEAR | Says remote, zero context |
| 6 | Indeed | RESTRICTED | Buried in paragraph 4 |
| 7 | LinkedIn | RESTRICTED | "Must reside in US or Canada" |
| 8 | WWR | WORLDWIDE | Multiple continents listed |
| 9 | LinkedIn | UNCLEAR | "Overlap with EST preferred" |
| 10 | Indeed | RESTRICTED | UK only, benefits mention NHS |

For each one: POST to `/api/classify`, compare result to expected, note mismatches.
Tune the prompt in `ClassificationService` until at least 9/10 are correct.

**Done when:** 9/10 test cases return the expected verdict.

---

## TASK 8 — Basic rate limiting

Prevent abuse before going public. Laravel has this built in.

In `routes/api.php`:
```php
Route::middleware('throttle:60,1')->group(function () {
    Route::post('/classify', [ClassifyController::class, 'classify']);
});
```

This allows 60 requests per minute per IP. Fine for now.

**Done when:** The 61st request within a minute returns a 429 response.

---

## Completion Checklist

- [ ] TASK 1 — Project created, server running
- [ ] TASK 2 — Environment configured
- [ ] TASK 3 — ClassificationService working in tinker
- [ ] TASK 4 — `/api/classify` endpoint responding correctly
- [ ] TASK 5 — Caching working, `cached: true` on repeat requests
- [ ] TASK 6 — CORS configured, no browser errors
- [ ] TASK 7 — 9/10 prompt accuracy on real listings
- [ ] TASK 8 — Rate limiting in place

---

## What's deliberately NOT here

- ❌ Auth / API keys (not needed for local MVP)
- ❌ User accounts
- ❌ Stripe / payments
- ❌ Production deployment
- ❌ Queue jobs (classification is fast enough to be synchronous for now)

All of the above come after the Chrome extension is working end to end locally.
