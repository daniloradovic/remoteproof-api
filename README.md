# RemoteProof API

Backend service that classifies remote job postings as **WORLDWIDE**, **RESTRICTED**, or **UNCLEAR** by sending the description through Claude. Built to power the [RemoteProof Chrome extension](https://remoteproof.app), but the HTTP surface is small enough to drop into any client.

## Why it exists

Remote job listings rarely state geographic restrictions clearly — the "must be authorized to work in the US" line is often buried in paragraph four. RemoteProof reads the listing and surfaces a verdict before you waste time applying.

## Stack

| Layer | Choice |
|---|---|
| Framework | Laravel 11 |
| Language | PHP 8.3 |
| AI | Anthropic Claude (`claude-sonnet-4-*`) |
| Database | SQLite (single-file, fits on a $14 droplet) |
| Cache & queue | File driver (swap to Redis when traffic justifies it) |
| Errors | Sentry |
| Metrics | Laravel Pulse at `/pulse` |
| Admin | Filament at `/admin` |

## Architecture

```
┌──────────────┐    POST /api/classify     ┌────────────────────┐
│   Extension  │ ────────────────────────▶ │   RemoteProof API  │
│  (Chrome)    │ ◀──────────────────────── │   (Laravel)        │
└──────────────┘   { verdict, cached, … }  └─────────┬──────────┘
                                                     │
                                       cache hit?    │ no
                                                     ▼
                                          ┌──────────────────┐
                                          │  Anthropic API   │
                                          └──────────────────┘

events table  ◀─── auto-tracked: anon_id, host, verdict, cached, latency
```

Cache hits never call Claude, never count against the daily budget, and are served instantly.

## API

### `POST /api/classify`

Classify a single job description.

**Headers**
- `Content-Type: application/json`
- `X-Anon-Id: <uuid>` — optional, for anonymous behavioural tracking. Truncated to 64 chars.

**Body**
```json
{
  "text": "Full job description, minimum 100 characters.",
  "url": "https://example.com/jobs/123"
}
```
- `text` — required, string, ≥100 chars
- `url` — optional. When present, the result is cached for 24 h keyed on the URL hash, and the hostname is stored for stats.

**Response (200)**
```json
{
  "verdict": "RESTRICTED",
  "confidence": "HIGH",
  "reason": "Requires US work authorization.",
  "signals": ["must be authorized to work in the US"],
  "cached": false
}
```

**Other status codes**
| Status | Meaning |
|---|---|
| `422` | Validation failed (`text` missing or too short, `url` malformed) |
| `429` | Rate limit (60 req/min/IP) **or** daily Anthropic cap reached |
| `502` | Anthropic call failed |

### `GET /api/health`

Used by uptime monitors. Not rate-limited, not tracked.

```json
{
  "ok": true,
  "version": "abc1234",
  "time": "2026-05-07T12:34:56+00:00"
}
```

## Local development

Requires PHP 8.3+, Composer 2, and Node 20+ (for Vite). SQLite ships with PHP — no DB server needed.

```bash
git clone https://github.com/daniloradovic/remoteproof-api.git
cd remoteproof-api

composer install
npm install

cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

# Set at minimum:
#   ANTHROPIC_API_KEY=sk-ant-…
#   ANTHROPIC_API_URL=https://api.anthropic.com/v1/messages
#   ANTHROPIC_MODEL=claude-sonnet-4-20250514
#   EXTENSION_ORIGIN=chrome-extension://<your-unpacked-id>
$EDITOR .env

php artisan serve
```

Smoke test:
```bash
curl -X POST http://localhost:8000/api/classify \
  -H "Content-Type: application/json" \
  -d '{"text": "Senior engineer role, fully remote. Must be authorized to work in the United States. Competitive salary and benefits package."}'
```

To use `/admin` and `/pulse`, create an admin user whose email is in `ADMIN_EMAILS`:
```bash
php artisan make:filament-user
```

## Tests

```bash
./vendor/bin/pest
```

The suite includes a benchmark of 10 real-world job listings (`tests/Feature/Services/ClassificationServiceTest.php`) that the prompt must score 9/10 on. Currently scoring 10/10.

## Environment variables

| Variable | Purpose | Example |
|---|---|---|
| `APP_VERSION` | Git short SHA, surfaced via `/api/health`. Set during deploy. | `abc1234` |
| `ANTHROPIC_API_KEY` | Anthropic credential. | `sk-ant-…` |
| `ANTHROPIC_API_URL` | API endpoint. | `https://api.anthropic.com/v1/messages` |
| `ANTHROPIC_MODEL` | Model ID. | `claude-sonnet-4-20250514` |
| `MAX_TOKENS` | Response token limit. | `1024` |
| `ANTHROPIC_DAILY_CAP` | Live calls per UTC day before returning 429. Cache hits don't count. | `5000` |
| `EXTENSION_ORIGIN` | Comma-separated CORS allowlist for the extension. Empty = closed. | `chrome-extension://abc123` |
| `LANDING_ORIGIN` | Comma-separated CORS allowlist for the marketing site. | `https://remoteproof.app` |
| `SENTRY_LARAVEL_DSN` | Empty disables Sentry. Release auto-tagged from `APP_VERSION`. | `https://…@sentry.io/0` |
| `SENTRY_TRACES_SAMPLE_RATE` | Fraction of requests traced. | `0.1` |
| `ADMIN_EMAILS` | Comma-separated allowlist for `/admin` and `/pulse`. Empty = no one. | `you@example.com` |

## Privacy

The API is designed to learn from usage without identifying users.

**Stored** in the `events` table on every classify call:
- `anon_id` — UUID generated by the extension and stored in `chrome.storage.local`
- `host` — hostname only (e.g. `linkedin.com`), never the full URL
- `verdict`, `cached`, `latency_ms`, `created_at`

**Not stored, ever:**
- Full URL or query string
- Job description text
- IP address
- Anything tying `anon_id` to a person

Cache keys (`classification:url:<sha1>`) hold the classification result for 24 h. The job text used to generate it is not retained.

## License

MIT.
