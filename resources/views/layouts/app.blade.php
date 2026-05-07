<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#fafaf9">
    <meta name="description" content="@yield('description', 'See whether a remote job is actually open to candidates worldwide — before you apply. Free Chrome extension that classifies any job listing as Worldwide, Restricted, or Unclear.')">

    <title>@yield('title', 'RemoteProof — is this remote job actually open to you?')</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-stone-50 text-slate-900 antialiased">
    <header class="border-b border-stone-200">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
            <a href="{{ route('landing') }}" class="flex items-center gap-2 font-semibold tracking-tight">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-indigo-600"></span>
                RemoteProof
            </a>
            <nav class="flex items-center gap-6 text-sm text-slate-600">
                <a href="{{ route('landing') }}#how" class="hover:text-slate-900">How it works</a>
                <a href="{{ route('landing') }}#faq" class="hover:text-slate-900">FAQ</a>
                <a href="{{ route('privacy') }}" class="hover:text-slate-900">Privacy</a>
                <a href="https://github.com/daniloradovic/remoteproof-api" class="hover:text-slate-900">GitHub</a>
            </nav>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="border-t border-stone-200 mt-24">
        <div class="mx-auto flex max-w-6xl flex-col gap-2 px-6 py-8 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
            <span>© {{ date('Y') }} RemoteProof. MIT licensed.</span>
            <div class="flex gap-5">
                <a href="{{ route('privacy') }}" class="hover:text-slate-900">Privacy</a>
                <a href="https://github.com/daniloradovic/remoteproof-api" class="hover:text-slate-900">GitHub</a>
                <a href="mailto:hello@remoteproof.app" class="hover:text-slate-900">Contact</a>
            </div>
        </div>
    </footer>
</body>
</html>
