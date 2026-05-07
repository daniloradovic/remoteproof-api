@extends('layouts.app')

@section('content')
    <section class="mx-auto max-w-6xl px-6 pt-20 pb-24 text-center">
        <span class="inline-flex items-center gap-2 rounded-full border border-slate-800 bg-slate-900/60 px-3 py-1 text-xs uppercase tracking-wider text-slate-400">
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
            Free Chrome extension
        </span>
        <h1 class="mt-6 text-4xl font-semibold tracking-tight sm:text-5xl md:text-6xl">
            Is this remote job<br>
            <span class="text-emerald-400">actually open to you?</span>
        </h1>
        <p class="mx-auto mt-6 max-w-2xl text-lg text-slate-300">
            Most "remote" listings hide a country requirement in paragraph four.
            RemoteProof reads the description and shows you the real answer in one badge —
            <strong class="text-emerald-300">Worldwide</strong>,
            <strong class="text-rose-300">Restricted</strong>,
            or <strong class="text-amber-300">Unclear</strong> — before you waste an application.
        </p>
        <div class="mt-10 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
            <a href="#install" class="inline-flex items-center justify-center rounded-md bg-emerald-500 px-6 py-3 font-medium text-slate-950 transition hover:bg-emerald-400">
                Add to Chrome
            </a>
            <a href="#how" class="inline-flex items-center justify-center rounded-md border border-slate-700 px-6 py-3 font-medium text-slate-200 transition hover:border-slate-500">
                How it works
            </a>
        </div>

        <div class="mx-auto mt-16 grid max-w-4xl gap-4 sm:grid-cols-3">
            @foreach ([
                ['verdict' => 'WORLDWIDE',  'class' => 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300', 'reason' => 'No timezone restrictions, hires across continents.'],
                ['verdict' => 'RESTRICTED', 'class' => 'border-rose-500/40 bg-rose-500/10 text-rose-300',           'reason' => 'Requires US work authorization.'],
                ['verdict' => 'UNCLEAR',    'class' => 'border-amber-500/40 bg-amber-500/10 text-amber-300',         'reason' => 'Says "remote" with no geographic context.'],
            ] as $sample)
                <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-5 text-left">
                    <span class="inline-flex items-center rounded-md border px-2 py-1 text-xs font-semibold tracking-wide {{ $sample['class'] }}">
                        {{ $sample['verdict'] }}
                    </span>
                    <p class="mt-3 text-sm text-slate-300">{{ $sample['reason'] }}</p>
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
                <div class="rounded-xl border border-slate-800 bg-slate-900/40 p-6">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-500/20 text-sm font-semibold text-emerald-300">
                        {{ $step['step'] }}
                    </div>
                    <h3 class="mt-4 font-semibold text-white">{{ $step['title'] }}</h3>
                    <p class="mt-2 text-sm text-slate-300">{{ $step['body'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-6 py-20">
        <h2 class="text-center text-3xl font-semibold tracking-tight">Real classifications</h2>
        <p class="mx-auto mt-3 max-w-2xl text-center text-slate-400">
            Drawn from the test suite — the same listings the prompt is benchmarked against.
        </p>
        <div class="mt-12 grid gap-6 md:grid-cols-3">
            <article class="rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-6">
                <span class="inline-block rounded-md border border-emerald-500/40 bg-emerald-500/10 px-2 py-1 text-xs font-semibold text-emerald-300">WORLDWIDE</span>
                <p class="mt-4 text-sm italic text-slate-300">"This role is fully remote and open to candidates anywhere in the world. There are no timezone restrictions and no requirement to relocate."</p>
                <p class="mt-4 text-xs uppercase tracking-wider text-slate-500">Toggl Track · Senior Backend Engineer</p>
            </article>
            <article class="rounded-xl border border-rose-500/20 bg-rose-500/5 p-6">
                <span class="inline-block rounded-md border border-rose-500/40 bg-rose-500/10 px-2 py-1 text-xs font-semibold text-rose-300">RESTRICTED</span>
                <p class="mt-4 text-sm italic text-slate-300">"Applicants must be authorized to work in the United States without sponsorship now or in the future."</p>
                <p class="mt-4 text-xs uppercase tracking-wider text-slate-500">Major US fintech · Software Engineer</p>
            </article>
            <article class="rounded-xl border border-amber-500/20 bg-amber-500/5 p-6">
                <span class="inline-block rounded-md border border-amber-500/40 bg-amber-500/10 px-2 py-1 text-xs font-semibold text-amber-300">UNCLEAR</span>
                <p class="mt-4 text-sm italic text-slate-300">"Remote, with overlap to EST hours preferred." No country mentioned, no payroll provider listed, no timezone hard requirement.</p>
                <p class="mt-4 text-xs uppercase tracking-wider text-slate-500">Anonymous startup · Product Engineer</p>
            </article>
        </div>
    </section>

    <section id="faq" class="mx-auto max-w-3xl px-6 py-20">
        <h2 class="text-center text-3xl font-semibold tracking-tight">FAQ</h2>
        <div class="mt-12 space-y-6">
            @foreach ([
                ['q' => 'Is my data private?', 'a' => 'Yes. The extension generates an anonymous UUID locally and sends only that, the hostname (e.g. linkedin.com), and the verdict to our API. We never store the full URL, the job description, or your IP. See the <a class="underline hover:text-white" href="' . route('privacy') . '">privacy page</a>.'],
                ['q' => 'How accurate is it?',  'a' => 'The classification prompt currently scores 10/10 on a benchmark of real-world listings sourced from LinkedIn, Indeed, and We Work Remotely. The benchmark is in the public repo and runs on every commit.'],
                ['q' => 'How much does it cost?', 'a' => 'Free. Cached results never call the AI, and a daily budget cap protects against runaway costs.'],
                ['q' => 'What sites are supported?', 'a' => 'Anywhere with a job description visible on the page. The extension reads the rendered text, not the site\'s API, so it works on LinkedIn, Indeed, WWR, Wellfound, individual company pages, and more.'],
                ['q' => 'Is there an API I can use?', 'a' => 'Yes — <code class="rounded bg-slate-800 px-1 py-0.5 text-xs">POST /api/classify</code> is documented in the <a class="underline hover:text-white" href="https://github.com/daniloradovic/remoteproof-api">README</a>. CORS is locked to the published extension and this site, so production keys aren\'t public.'],
            ] as $item)
                <details class="rounded-lg border border-slate-800 bg-slate-900/40 px-5 py-4 open:bg-slate-900/70">
                    <summary class="cursor-pointer list-none font-medium text-white">{{ $item['q'] }}</summary>
                    <p class="mt-3 text-sm text-slate-300">{!! $item['a'] !!}</p>
                </details>
            @endforeach
        </div>
    </section>

    <section id="install" class="mx-auto max-w-3xl px-6 pt-10 pb-20 text-center">
        <h2 class="text-3xl font-semibold tracking-tight">Stop applying to jobs you can't take.</h2>
        <p class="mx-auto mt-4 max-w-xl text-slate-300">Install RemoteProof and see the real geographic scope of every remote listing you read.</p>
        <a href="#install" class="mt-8 inline-flex items-center justify-center rounded-md bg-emerald-500 px-6 py-3 font-medium text-slate-950 transition hover:bg-emerald-400">
            Add to Chrome
        </a>
    </section>
@endsection
