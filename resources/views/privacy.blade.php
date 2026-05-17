@extends('layouts.app')

@section('title', 'Privacy — RemoteProof')
@section('description', 'How RemoteProof handles your data: anonymous UUIDs, hostname-only logging, no job description text stored, no IPs, no tracking pixels.')

@section('content')
    <article class="mx-auto max-w-3xl px-6 py-16">
        <h1 class="text-3xl font-semibold tracking-tight">Privacy</h1>
        <p class="mt-4 text-slate-500">Last updated: {{ \Illuminate\Support\Carbon::now()->format('F Y') }}</p>

        <p class="mt-8 text-slate-700">RemoteProof is built to learn from how the extension is used without identifying who is using it. The contract below is enforced in code and visible in the open-source repository.</p>

        <h2 class="mt-12 text-xl font-semibold text-slate-900">What we store</h2>
        <ul class="mt-4 list-disc space-y-2 pl-5 text-slate-700">
            <li><strong>Anonymous UUID.</strong> Generated locally on first install and stored in <code class="rounded bg-stone-100 px-1 py-0.5 text-xs text-slate-800">chrome.storage.local</code>. It is sent with each classification request as the <code class="rounded bg-stone-100 px-1 py-0.5 text-xs text-slate-800">X-Anon-Id</code> header. It is not tied to your email, name, IP, or any other identifier we hold.</li>
            <li><strong>Hostname only.</strong> When you classify a job, we store the hostname of the listing (e.g. <code class="rounded bg-stone-100 px-1 py-0.5 text-xs text-slate-800">linkedin.com</code>). We never store the full URL or query string.</li>
            <li><strong>The verdict.</strong> Whether the job was classified Worldwide, Restricted, or Unclear, plus whether the result came from cache and how long the request took.</li>
        </ul>

        <h2 class="mt-12 text-xl font-semibold text-slate-900">What we never store</h2>
        <ul class="mt-4 list-disc space-y-2 pl-5 text-slate-700">
            <li>The full URL of the listing.</li>
            <li>The job description text. It is sent to our API, used for classification, and discarded.</li>
            <li>Your IP address (it is used by the rate limiter in memory only and never written to disk).</li>
            <li>Cookies, fingerprints, or any tracking pixels.</li>
        </ul>

        <h2 class="mt-12 text-xl font-semibold text-slate-900">Caching</h2>
        <p class="mt-4 text-slate-700">When a URL is classified, we store the resulting verdict for 24 hours keyed by a SHA-1 hash of the URL. The job text used to produce the verdict is not retained — only the four-field result.</p>

        <h2 class="mt-12 text-xl font-semibold text-slate-900">Waitlist emails</h2>
        <p class="mt-4 text-slate-700">If you join the Pro waitlist on this site, we store the email address you submitted, which plan you expressed interest in, and the page you signed up from. We use this only to email you when Pro becomes available and to gauge demand. You can ask us to delete your waitlist entry at any time by emailing <a class="text-indigo-600 underline hover:text-indigo-700" href="mailto:hello@remoteproof.app">hello@remoteproof.app</a>. Waitlist data is kept separate from the anonymous classification events above.</p>

        <h2 class="mt-12 text-xl font-semibold text-slate-900">Third parties</h2>
        <p class="mt-4 text-slate-700">Job description text is sent to <a class="text-indigo-600 underline hover:text-indigo-700" href="https://www.anthropic.com/">Anthropic</a> for classification, governed by their <a class="text-indigo-600 underline hover:text-indigo-700" href="https://www.anthropic.com/legal/privacy">privacy policy</a>. Errors are sent to Sentry without request bodies. Uptime pings hit a no-data health endpoint.</p>

        <h2 class="mt-12 text-xl font-semibold text-slate-900">Your rights</h2>
        <p class="mt-4 text-slate-700">Because we never tie the anonymous UUID to anything that identifies you, we cannot find or delete your data on request. You can stop new data being recorded immediately by uninstalling the extension. Existing event rows are pruned after 90 days.</p>

        <h2 class="mt-12 text-xl font-semibold text-slate-900">Contact</h2>
        <p class="mt-4 text-slate-700">Questions or concerns: <a class="text-indigo-600 underline hover:text-indigo-700" href="mailto:hello@remoteproof.app">hello@remoteproof.app</a>.</p>
    </article>
@endsection
