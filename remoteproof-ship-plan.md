# RemoteProof — Ship Plan
> From "works on localhost" to "live, observable, and safe to scale"

---

## Stack additions

| Concern | Pick | Why |
|---|---|---|
| Error tracking | Sentry (`sentry/sentry-laravel`) | Free tier, alerting, release tracking |
| Metrics | Laravel Pulse | First-party, no infra, scales to Redis when you outgrow file driver |
| Admin panel | Filament v3 | Free, Laravel 11 native, fast resource scaffolding |
| Uptime | Better Stack (free tier) | 1-min pings on `/api/health`, email + Slack alerts |
| Hosting | Laravel Forge + DigitalOcean ($14 droplet) | Boring, single-server, room to grow into Redis/queues |
| Anon tracking | Custom `events` table + UUID header | No third party, GDPR-clean, owns the data |

**Deliberately deferred:** Redis cache/queue, Horizon, Telescope, Vapor, multi-tenant auth. Add when traffic justifies — file driver + sync queue are fine for MVP.

---

## Phase 1 — Ship blockers

These gate the public deploy. Skipping any of them means either losing money (Anthropic abuse), losing trust (silent 5xx), or getting hard-blocked (CORS).

### TASK 9 — Lock down CORS

`config/cors.php`:

```php
'paths' => ['api/*'],
'allowed_origins' => [
    env('EXTENSION_ORIGIN'),       // chrome-extension://<published-id>
    env('LANDING_ORIGIN'),         // https://remoteproof.app
],
'allowed_methods' => ['POST', 'GET', 'OPTIONS'],
'allowed_headers' => ['Content-Type', 'Accept', 'X-Anon-Id'],
```

Add to `.env.example`:
```
EXTENSION_ORIGIN=
LANDING_ORIGIN=
```

**Done when:** a curl from a non-allowlisted origin gets blocked at the preflight, the extension still works.

---

### TASK 10 — Anthropic daily budget guard

Without this, one scraper can drain your Anthropic balance in an afternoon.

Add to `ClassifyController` before calling the service:

```php
$dailyKey = 'anthropic:spend:'.now()->format('Y-m-d');
$dailyCount = Cache::get($dailyKey, 0);
$dailyCap = (int) config('services.anthropic.daily_cap', 5000);

if ($dailyCount >= $dailyCap) {
    return response()->json(['error' => 'Daily classification cap reached. Try again tomorrow.'], 429);
}
```

After a successful (non-cached) classification:
```php
Cache::increment($dailyKey);
Cache::put($dailyKey, Cache::get($dailyKey), now()->endOfDay());
```

Add `ANTHROPIC_DAILY_CAP=5000` to `.env.example` and `config/services.php`.

**Done when:** the 5001st live classification of the day returns 429 with a clear message.

---

### TASK 11 — `/api/health` endpoint

```php
// routes/api.php — outside the throttle group
Route::get('/health', function () {
    return response()->json([
        'ok' => true,
        'version' => trim((string) shell_exec('git rev-parse --short HEAD')) ?: 'unknown',
        'time' => now()->toIso8601String(),
    ]);
});
```

**Done when:** `curl https://api.remoteproof.app/api/health` returns 200 with the current commit SHA.

---

### TASK 12 — Sentry

```bash
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=<dsn>
```

In `bootstrap/app.php` add Sentry to the exception handler. Set `SENTRY_TRACES_SAMPLE_RATE=0.1` so you get traces without paying for every request.

**Done when:** throwing a test exception shows up in the Sentry UI within 30 seconds.

---

### TASK 13 — Uptime monitoring

No code. Sign up for Better Stack free tier, add a monitor pinging `https://api.remoteproof.app/api/health` every 60s, alert to email + Slack on 2 consecutive failures.

**Done when:** taking the server down for 2 minutes triggers an email.

---

## Phase 2 — Visibility

You can ship without Phase 2, but you'll be flying blind on day 2.

### TASK 14 — Events table

```bash
php artisan make:model Event -m
```

Migration:
```php
Schema::create('events', function (Blueprint $table) {
    $table->id();
    $table->string('anon_id', 64)->index();
    $table->string('name', 64)->index();      // e.g. classify.completed
    $table->string('host', 255)->nullable()->index();   // hostname only, never full URL
    $table->string('verdict', 16)->nullable()->index();
    $table->boolean('cached')->default(false);
    $table->unsignedInteger('latency_ms')->nullable();
    $table->json('properties')->nullable();
    $table->timestamp('created_at')->index();
});
```

**Privacy rule:** never store full URL, never store job description text. Hostname + verdict only.

**Done when:** `php artisan migrate` creates the table.

---

### TASK 15 — Server-side auto-tracking on classify

Inside `ClassifyController::classify`, after building the response:

```php
Event::create([
    'anon_id' => substr((string) $request->header('X-Anon-Id', 'unknown'), 0, 64),
    'name' => 'classify.completed',
    'host' => $request->input('url') ? parse_url($request->input('url'), PHP_URL_HOST) : null,
    'verdict' => $result['verdict'],
    'cached' => $cached,
    'latency_ms' => (int) ((microtime(true) - $start) * 1000),
    'created_at' => now(),
]);
```

Wrap in a try/catch — tracking failures must never break the API response.

The extension generates a UUID v4 once, stores it in `chrome.storage.local`, sends it as `X-Anon-Id` on every classify call. No separate `/api/events` endpoint until you actually need extension-side events.

**Done when:** five classify calls produce five `events` rows, each tagged with the same `anon_id` for the same browser.

---

### TASK 16 — Laravel Pulse

```bash
composer require laravel/pulse
php artisan vendor:publish --provider="Laravel\\Pulse\\PulseServiceProvider"
php artisan migrate
```

Restrict access in `app/Providers/AppServiceProvider.php`:
```php
Gate::define('viewPulse', fn ($user) => in_array($user->email, config('app.admin_emails', [])));
```

`ADMIN_EMAILS=you@example.com` in `.env`.

**Done when:** `/pulse` shows live request rate, slow endpoints, and exception count behind your login.

---

### TASK 17 — Filament shell + Events resource

```bash
composer require filament/filament:"^3.2"
php artisan filament:install --panels
php artisan make:filament-user
php artisan make:filament-resource Event --view
```

In the generated `EventResource`, add columns: `created_at`, `name`, `verdict`, `host`, `cached`, `latency_ms`. Add filters by `verdict` and date range.

Gate access by reusing `viewPulse` logic (same admin allowlist). Mount at `/admin`.

Add three dashboard widgets later (post-launch, when data is interesting):
- Today's Anthropic spend (read the `anthropic:spend:*` cache key)
- 7-day verdict distribution (pie chart from events)
- Top 20 hostnames classified

**Done when:** logging into `/admin` shows a browseable, filterable list of events.

---

## Phase 3 — Launch surface

### TASK 18 — README rewrite

Replace the Laravel boilerplate. Sections:
1. **What it is** — one paragraph, links to the extension
2. **API reference** — `POST /api/classify` and `GET /api/health`, request/response examples
3. **Local dev** — `composer install`, copy `.env.example`, `php artisan key:generate`, `php artisan migrate`, `php artisan serve`, the curl example
4. **Env vars** — table of every var with purpose and example
5. **Architecture** — extension → API → Anthropic, with the cache + events layer
6. **Privacy** — what's stored (hostname, verdict, anon UUID), what's not (full URL, job text, IP)
7. **License**

**Done when:** a stranger can clone the repo and have it answering classify requests in under 10 minutes using only the README.

---

### TASK 19 — Landing page

Single Blade view at `/`, Tailwind via existing Vite. Sections:
- Hero: product name, one-liner, install-from-Chrome-Web-Store button, animated verdict badge demo
- "How it works" — 3 steps with icons
- Three sample classifications (real ones from the benchmark)
- FAQ — privacy, accuracy, pricing
- Footer — GitHub link, privacy policy, contact

`routes/web.php`:
```php
Route::view('/', 'landing')->name('landing');
Route::view('/privacy', 'privacy')->name('privacy');
```

Defer a separate marketing repo / Next.js until you need a blog or pricing page.

**Done when:** `https://remoteproof.app` loads, hero CTA links to the live extension listing, Lighthouse score ≥ 90 on mobile.

---

## Phase 4 — Deploy

### TASK 20 — Forge + DigitalOcean

1. Spin up a $14 DO droplet via Forge (Ubuntu 24.04, PHP 8.3, nginx)
2. Connect the GitHub repo, enable quick deploy on `main`
3. Set env vars in Forge: `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, `ANTHROPIC_API_KEY`, `SENTRY_LARAVEL_DSN`, `EXTENSION_ORIGIN`, `LANDING_ORIGIN`, `ADMIN_EMAILS`, `ANTHROPIC_DAILY_CAP`
4. Provision SSL via Forge's Let's Encrypt integration on `api.remoteproof.app` and `remoteproof.app`
5. Deploy script: `composer install --no-dev`, `php artisan migrate --force`, `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`
6. Point Better Stack monitor at the live `/api/health`

**Done when:** push to `main` ends with a green Sentry release marker, Better Stack green, and a real classify curl returning the right verdict from the live URL.

---

## Completion checklist

**Phase 1 — Ship blockers**
- [ ] TASK 9 — CORS locked to extension + landing origins
- [ ] TASK 10 — Anthropic daily budget guard
- [ ] TASK 11 — `/api/health` endpoint
- [ ] TASK 12 — Sentry integrated
- [ ] TASK 13 — Better Stack uptime monitor

**Phase 2 — Visibility**
- [ ] TASK 14 — `events` table migrated
- [ ] TASK 15 — Server-side auto-tracking on classify
- [ ] TASK 16 — Laravel Pulse mounted at `/pulse`
- [ ] TASK 17 — Filament admin at `/admin` with Events resource

**Phase 3 — Launch surface**
- [ ] TASK 18 — README rewritten
- [ ] TASK 19 — Landing page live

**Phase 4 — Deploy**
- [ ] TASK 20 — Live on Forge + DigitalOcean with SSL

---

## Scale-up triggers (revisit when you hit these)

| Trigger | What to add |
|---|---|
| > 1 req/sec sustained | Switch cache + queue from `file` to Redis |
| Anthropic costs > $50/day | Move classification behind a queued job, return 202 + webhook |
| > 100k events/month | Aggregate events nightly into a `daily_stats` table, prune raw events at 90 days |
| Multiple admins | Replace email allowlist with a real role/permission system (spatie/laravel-permission) |
| Need a blog or pricing | Move landing page to a separate Next.js repo |

---

## What's deliberately NOT here

- ❌ User accounts / sign-up flow (extension users stay anonymous)
- ❌ Stripe / payments (free product for now)
- ❌ Redis (file driver fits a single droplet)
- ❌ Horizon / queues (classification is < 2s, sync is fine)
- ❌ Telescope (Pulse + Sentry cover it; Telescope is heavyweight in prod)
- ❌ Webhooks / async classify (only when costs force it)
- ❌ Custom Filament dashboard widgets (build after a week of real data)
