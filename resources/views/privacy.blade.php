@extends('layouts.app')

@section('title', 'Privacy — RemoteProof')
@section('description', 'How RemoteProof handles your data: anonymous UUIDs, hostname-only logging, no job description text stored, no IPs, no tracking pixels.')

@section('content')
    <article class="mx-auto max-w-3xl px-6 py-16 prose prose-invert prose-slate">
        <h1 class="text-3xl font-semibold tracking-tight">Privacy</h1>
        <p class="mt-4 text-slate-300">Last updated: {{ \Illuminate\Support\Carbon::now()->format('F Y') }}</p>

        <p class="mt-8 text-slate-300">RemoteProof is built to learn from how the extension is used without identifying who is using it. The contract below is enforced in code and visible in the open-source repository.</p>

        <h2 class="mt-12 text-xl font-semibold text-white">What we store</h2>
        <ul class="mt-4 list-disc space-y-2 pl-5 text-slate-300">
            <li><strong>Anonymous UUID.</strong> Generated locally on first install and stored in <code class="rounded bg-slate-800 px-1 py-0.5 text-xs">chrome.storage.local</code>. It is sent with each classification request as the <code class="rounded bg-slate-800 px-1 py-0.5 text-xs">X-Anon-Id</code> header. It is not tied to your email, name, IP, or any other identifier we hold.</li>
            <li><strong>Hostname only.</strong> When you classify a job, we store the hostname of the listing (e.g. <code class="rounded bg-slate-800 px-1 py-0.5 text-xs">linkedin.com</code>). We never store the full URL or query string.</li>
            <li><strong>The verdict.</strong> Whether the job was classified Worldwide, Restricted, or Unclear, plus whether the result came from cache and how long the request took.</li>
        </ul>

        <h2 class="mt-12 text-xl font-semibold text-white">What we never store</h2>
        <ul class="mt-4 list-disc space-y-2 pl-5 text-slate-300">
            <li>The full URL of the listing.</li>
            <li>The job description text. It is sent to our API, used for classification, and discarded.</li>
            <li>Your IP address (it is used by the rate limiter in memory only and never written to disk).</li>
            <li>Cookies, fingerprints, or any tracking pixels.</li>
        </ul>

        <h2 class="mt-12 text-xl font-semibold text-white">Caching</h2>
        <p class="mt-4 text-slate-300">When a URL is classified, we store the resulting verdict for 24 hours keyed by a SHA-1 hash of the URL. The job text used to produce the verdict is not retained — only the four-field result.</p>

        <h2 class="mt-12 text-xl font-semibold text-white">Third parties</h2>
        <p class="mt-4 text-slate-300">Job description text is sent to <a class="underline hover:text-white" href="https://www.anthropic.com/">Anthropic</a> for classification, governed by their <a class="underline hover:text-white" href="https://www.anthropic.com/legal/privacy">privacy policy</a>. Errors are sent to Sentry without request bodies. Uptime pings hit a no-data health endpoint.</p>

        <h2 class="mt-12 text-xl font-semibold text-white">Your rights</h2>
        <p class="mt-4 text-slate-300">Because we never tie the anonymous UUID to anything that identifies you, we cannot find or delete your data on request. You can stop new data being recorded immediately by uninstalling the extension. Existing event rows are pruned after 90 days.</p>

        <h2 class="mt-12 text-xl font-semibold text-white">Contact</h2>
        <p class="mt-4 text-slate-300">Questions or concerns: <a class="underline hover:text-white" href="mailto:hello@remoteproof.app">hello@remoteproof.app</a>.</p>
    </article>
@endsection
