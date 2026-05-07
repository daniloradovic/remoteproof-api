<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <meta name="description" content="@yield('description', 'See whether a remote job is actually open to candidates worldwide — before you apply. Free Chrome extension that classifies any job listing as Worldwide, Restricted, or Unclear.')">

    <title>@yield('title', 'RemoteProof — is this remote job actually open to you?')</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    <header class="border-b border-slate-800/60">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-5">
            <a href="{{ route('landing') }}" class="flex items-center gap-2 font-semibold tracking-tight">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                RemoteProof
            </a>
            <nav class="flex items-center gap-6 text-sm text-slate-300">
                <a href="{{ route('landing') }}#how" class="hover:text-white">How it works</a>
                <a href="{{ route('landing') }}#faq" class="hover:text-white">FAQ</a>
                <a href="{{ route('privacy') }}" class="hover:text-white">Privacy</a>
                <a href="https://github.com/daniloradovic/remoteproof-api" class="hover:text-white">GitHub</a>
            </nav>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer class="border-t border-slate-800/60 mt-24">
        <div class="mx-auto flex max-w-6xl flex-col gap-2 px-6 py-8 text-sm text-slate-400 sm:flex-row sm:items-center sm:justify-between">
            <span>© {{ date('Y') }} RemoteProof. MIT licensed.</span>
            <div class="flex gap-5">
                <a href="{{ route('privacy') }}" class="hover:text-white">Privacy</a>
                <a href="https://github.com/daniloradovic/remoteproof-api" class="hover:text-white">GitHub</a>
                <a href="mailto:hello@remoteproof.app" class="hover:text-white">Contact</a>
            </div>
        </div>
    </footer>
</body>
</html>
