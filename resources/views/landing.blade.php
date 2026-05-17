@extends('layouts.app')

@section('content')
    <section class="mx-auto max-w-6xl px-6 pt-20 pb-24 text-center">
        <span class="inline-flex items-center gap-2 rounded-full border border-stone-200 bg-white px-3 py-1 text-xs uppercase tracking-wider text-slate-500 shadow-sm">
            <span class="h-1.5 w-1.5 rounded-full bg-indigo-600"></span>
            Free Chrome extension
        </span>
        <h1 class="mt-6 text-4xl font-semibold tracking-tight sm:text-5xl md:text-6xl">
            Is this remote job<br>
            <span class="text-indigo-600">actually open to you?</span>
        </h1>
        <p class="mx-auto mt-6 max-w-2xl text-lg text-slate-600">
            Most "remote" listings hide a country requirement in paragraph four.
            RemoteProof reads the description and shows you the real answer in one badge —
            <strong class="text-emerald-700">Worldwide</strong>,
            <strong class="text-rose-700">Restricted</strong>,
            or <strong class="text-amber-700">Unclear</strong> — before you waste an application.
        </p>
        <div class="mt-10 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
            <a href="https://chromewebstore.google.com/detail/remoteproof/enjbdkijfnmdenkcjdleldcfgjfemflh" target="_blank" rel="noopener" class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-6 py-3 font-medium text-white shadow-sm transition hover:bg-indigo-500">
                Add to Chrome
            </a>
            <a href="#how" class="inline-flex items-center justify-center rounded-md border border-slate-300 bg-white px-6 py-3 font-medium text-slate-700 transition hover:border-slate-400">
                How it works
            </a>
        </div>

        <div class="mx-auto mt-16 grid max-w-4xl gap-4 sm:grid-cols-3">
            @foreach ([
                ['verdict' => 'WORLDWIDE',  'class' => 'border-emerald-200 bg-emerald-50 text-emerald-700', 'reason' => 'No timezone restrictions, hires across continents.'],
                ['verdict' => 'RESTRICTED', 'class' => 'border-rose-200 bg-rose-50 text-rose-700',           'reason' => 'Requires US work authorization.'],
                ['verdict' => 'UNCLEAR',    'class' => 'border-amber-200 bg-amber-50 text-amber-700',         'reason' => 'Says "remote" with no geographic context.'],
            ] as $sample)
                <div class="rounded-xl border border-stone-200 bg-white p-5 text-left shadow-sm">
                    <span class="inline-flex items-center rounded-md border px-2 py-1 text-xs font-semibold tracking-wide {{ $sample['class'] }}">
                        {{ $sample['verdict'] }}
                    </span>
                    <p class="mt-3 text-sm text-slate-600">{{ $sample['reason'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section id="how" class="mx-auto max-w-6xl px-6 py-20">
        <h2 class="text-center text-3xl font-semibold tracking-tight">How it works</h2>
        <div class="mt-12 grid gap-6 md:grid-cols-3">
            @foreach ([
                ['step' => '1', 'title' => 'Install the extension', 'body' => 'One click from the Chrome Web Store. No account, no signup.'],
                ['step' => '2', 'title' => 'Open any job posting',   'body' => 'LinkedIn, Indeed, We Work Remotely, Wellfound — works anywhere with a job description.'],
                ['step' => '3', 'title' => 'See the verdict',         'body' => 'A coloured badge appears on the page within a second. Tap it for the reasoning and signals.'],
            ] as $step)
                <div class="rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-50 text-sm font-semibold text-indigo-600">
                        {{ $step['step'] }}
                    </div>
                    <h3 class="mt-4 font-semibold text-slate-900">{{ $step['title'] }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ $step['body'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-6 py-20">
        <h2 class="text-center text-3xl font-semibold tracking-tight">Real classifications</h2>
        <p class="mx-auto mt-3 max-w-2xl text-center text-slate-500">
            Drawn from the test suite — the same listings the prompt is benchmarked against.
        </p>
        <div class="mt-12 grid gap-6 md:grid-cols-3">
            <article class="rounded-xl border border-emerald-200 bg-emerald-50 p-6">
                <span class="inline-block rounded-md border border-emerald-300 bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-700">WORLDWIDE</span>
                <p class="mt-4 text-sm italic text-slate-700">"This role is fully remote and open to candidates anywhere in the world. There are no timezone restrictions and no requirement to relocate."</p>
                <p class="mt-4 text-xs uppercase tracking-wider text-slate-500">Toggl Track · Senior Backend Engineer</p>
            </article>
            <article class="rounded-xl border border-rose-200 bg-rose-50 p-6">
                <span class="inline-block rounded-md border border-rose-300 bg-rose-100 px-2 py-1 text-xs font-semibold text-rose-700">RESTRICTED</span>
                <p class="mt-4 text-sm italic text-slate-700">"Applicants must be authorized to work in the United States without sponsorship now or in the future."</p>
                <p class="mt-4 text-xs uppercase tracking-wider text-slate-500">Major US fintech · Software Engineer</p>
            </article>
            <article class="rounded-xl border border-amber-200 bg-amber-50 p-6">
                <span class="inline-block rounded-md border border-amber-300 bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-700">UNCLEAR</span>
                <p class="mt-4 text-sm italic text-slate-700">"Remote, with overlap to EST hours preferred." No country mentioned, no payroll provider listed, no timezone hard requirement.</p>
                <p class="mt-4 text-xs uppercase tracking-wider text-slate-500">Anonymous startup · Product Engineer</p>
            </article>
        </div>
    </section>

    <section id="faq" class="mx-auto max-w-3xl px-6 py-20">
        <h2 class="text-center text-3xl font-semibold tracking-tight">FAQ</h2>
        <div class="mt-12 space-y-6">
            @foreach ([
                ['q' => 'Is my data private?', 'a' => 'Yes. The extension generates an anonymous UUID locally and sends only that, the hostname (e.g. linkedin.com), and the verdict to our API. We never store the full URL, the job description, or your IP. See the <a class="text-indigo-600 underline hover:text-indigo-700" href="' . route('privacy') . '">privacy page</a>.'],
                ['q' => 'How accurate is it?',  'a' => 'The classification prompt currently scores 10/10 on a benchmark of real-world listings sourced from LinkedIn, Indeed, and We Work Remotely. The benchmark is in the public repo and runs on every commit.'],
                ['q' => 'How much does it cost?', 'a' => 'Free. Cached results never call the AI, and a daily budget cap protects against runaway costs.'],
                ['q' => 'What sites are supported?', 'a' => 'Anywhere with a job description visible on the page. The extension reads the rendered text, not the site\'s API, so it works on LinkedIn, Indeed, WWR, Wellfound, individual company pages, and more.'],
                ['q' => 'Is there an API I can use?', 'a' => 'Yes — <code class="rounded bg-stone-100 px-1 py-0.5 text-xs text-slate-800">POST /api/classify</code> is documented in the <a class="text-indigo-600 underline hover:text-indigo-700" href="https://github.com/daniloradovic/remoteproof-api">README</a>. CORS is locked to the published extension and this site, so production keys aren\'t public.'],
            ] as $item)
                <details class="rounded-lg border border-stone-200 bg-white px-5 py-4 shadow-sm open:bg-stone-50">
                    <summary class="cursor-pointer list-none font-medium text-slate-900">{{ $item['q'] }}</summary>
                    <p class="mt-3 text-sm text-slate-600">{!! $item['a'] !!}</p>
                </details>
            @endforeach
        </div>
    </section>

    <section id="pricing" class="mx-auto max-w-5xl px-6 py-20">
        <h2 class="text-center text-3xl font-semibold tracking-tight">Pricing</h2>
        <p class="mx-auto mt-3 max-w-2xl text-center text-slate-500">
            RemoteProof is free while we're in beta. Paid plans are coming for power users.
        </p>
        <div class="mx-auto mt-12 grid max-w-3xl items-stretch gap-6 md:grid-cols-2">
            <div class="grid grid-rows-[auto_1fr_auto] rounded-xl border border-stone-200 bg-white p-6 shadow-sm">
                <div>
                    <h3 class="text-lg font-semibold text-slate-900">Free</h3>
                    <p class="mt-1 text-sm text-slate-500">For casual job seekers.</p>
                </div>
                <ul class="mt-6 space-y-2 text-sm text-slate-700">
                    <li>50 classifications per month</li>
                    <li>No signup required</li>
                    <li>24-hour result caching</li>
                </ul>
                <div class="mt-8 space-y-2">
                    <div aria-hidden="true" class="invisible block w-full rounded-md border border-slate-300 px-3 py-2 text-sm">&nbsp;</div>
                    <a href="https://chromewebstore.google.com/detail/remoteproof/enjbdkijfnmdenkcjdleldcfgjfemflh" target="_blank" rel="noopener" class="inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-6 py-2.5 font-medium text-white shadow-sm transition hover:bg-indigo-500">
                        Add to Chrome
                    </a>
                </div>
            </div>
            <div class="grid grid-rows-[auto_1fr_auto] rounded-xl border border-indigo-200 bg-indigo-50/40 p-6 shadow-sm">
                <div>
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-slate-900">Pro</h3>
                        <span class="inline-flex items-center rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700">Coming soon</span>
                    </div>
                    <p class="mt-1 text-sm text-slate-500">For people applying daily.</p>
                </div>
                <ul class="mt-6 space-y-2 text-sm text-slate-700">
                    <li>2,000 classifications per month</li>
                    <li>Priority processing</li>
                    <li>Early access to new features</li>
                </ul>
                <form id="waitlist-form" class="mt-8 space-y-2" novalidate>
                    <label for="waitlist-email" class="sr-only">Email address</label>
                    <input id="waitlist-email" name="email" type="email" required autocomplete="email" placeholder="you@example.com" class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    <button type="submit" id="waitlist-submit" class="inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-6 py-2.5 font-medium text-white shadow-sm transition hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-60">
                        Join the waitlist
                    </button>
                    <p id="waitlist-message" class="hidden text-sm" role="status" aria-live="polite"></p>
                </form>
            </div>
        </div>
    </section>

    <section id="install" class="mx-auto max-w-3xl px-6 pt-10 pb-20 text-center">
        <h2 class="text-3xl font-semibold tracking-tight">Stop applying to jobs you can't take.</h2>
        <p class="mx-auto mt-4 max-w-xl text-slate-600">Install RemoteProof and see the real geographic scope of every remote listing you read.</p>
        <a href="https://chromewebstore.google.com/detail/remoteproof/enjbdkijfnmdenkcjdleldcfgjfemflh" target="_blank" rel="noopener" class="mt-8 inline-flex items-center justify-center rounded-md bg-indigo-600 px-6 py-3 font-medium text-white shadow-sm transition hover:bg-indigo-500">
            Add to Chrome
        </a>
    </section>

    <script>
        (function () {
            const form = document.getElementById('waitlist-form');
            if (!form) return;
            const emailInput = document.getElementById('waitlist-email');
            const submitBtn = document.getElementById('waitlist-submit');
            const message = document.getElementById('waitlist-message');

            function showMessage(text, kind) {
                message.textContent = text;
                message.className = 'text-sm ' + (kind === 'success'
                    ? 'text-emerald-700'
                    : 'text-rose-700');
            }

            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                const email = (emailInput.value || '').trim();
                if (!email) {
                    showMessage('Please enter your email address.', 'error');
                    return;
                }

                submitBtn.disabled = true;
                const originalLabel = submitBtn.textContent;
                submitBtn.textContent = 'Joining…';
                message.className = 'hidden';

                try {
                    const headers = {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    };
                    try {
                        const anonId = window.localStorage.getItem('remoteproof.anonId');
                        if (anonId) headers['X-Anon-Id'] = anonId;
                    } catch (_) { /* localStorage may be blocked */ }

                    const response = await fetch('/api/waitlist', {
                        method: 'POST',
                        headers: headers,
                        body: JSON.stringify({
                            email: email,
                            plan_interest: 'pro',
                            source: 'landing_pricing',
                        }),
                    });

                    if (response.ok) {
                        const data = await response.json().catch(() => ({}));
                        form.reset();
                        showMessage(
                            data.already_signed_up
                                ? "You're already on the list — we'll be in touch."
                                : "You're on the list. We'll email when Pro is ready.",
                            'success'
                        );
                    } else if (response.status === 422) {
                        const data = await response.json().catch(() => ({}));
                        showMessage(data.error || 'Please check your email address.', 'error');
                    } else if (response.status === 429) {
                        showMessage('Too many attempts. Please try again in a minute.', 'error');
                    } else {
                        showMessage('Something went wrong. Please try again.', 'error');
                    }
                } catch (_) {
                    showMessage('Network error. Please try again.', 'error');
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalLabel;
                }
            });
        })();
    </script>
@endsection
