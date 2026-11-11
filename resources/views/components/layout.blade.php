<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'DevOps Shikhbo' }} — learn.bazardev.com</title>
    <meta name="description" content="Learn DevOps from zero — in English and Bangla, with real projects and free resources.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.10.0/styles/github-dark.min.css">
</head>
<body class="bg-gray-50 text-gray-900 antialiased">
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3">
            <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-bold tracking-tight">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-gray-900 text-sm text-white">⌘</span>
                DevOps Shikhbo
            </a>

            <nav class="flex items-center gap-1 sm:gap-2" aria-label="Account">
                <a href="https://github.com/Nirjhor3029/devops-for-developers" target="_blank" rel="noopener"
                   class="hidden rounded-lg px-3 py-2 text-sm text-gray-500 transition-colors hover:text-gray-900 sm:block">GitHub</a>

                @guest
                    <a href="{{ route('login') }}"
                       class="rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100">Log in</a>
                    <a href="{{ route('register') }}"
                       class="rounded-lg bg-blue-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700">Sign up free</a>
                @else
                    @php $me = auth()->user(); @endphp
                    <a href="{{ route('learning.dashboard') }}"
                       class="rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ request()->routeIs('learning.dashboard') ? 'bg-blue-50 text-blue-700' : 'text-gray-700 hover:bg-gray-100' }}">My Learning</a>
                    <details class="relative" data-dropdown>
                        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-full py-1 pr-2 pl-1 transition-colors hover:bg-gray-100 [&::-webkit-details-marker]:hidden"
                                 aria-label="Open account menu">
                            <span class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">{{ $me->initials() }}</span>
                            <span class="hidden max-w-32 truncate text-sm font-medium text-gray-700 sm:block">{{ \Illuminate\Support\Str::before($me->name, ' ') }}</span>
                            <svg class="h-4 w-4 text-gray-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd"/></svg>
                        </summary>

                        <div class="absolute right-0 z-40 mt-2 w-64 rounded-xl border border-gray-100 bg-white p-1.5 shadow-lg">
                            <div class="border-b border-gray-100 px-3 py-2.5">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ $me->name }}</p>
                                <p class="truncate text-xs text-gray-500">{{ $me->email }}</p>
                            </div>
                            <div class="py-1.5">
                                <a href="{{ route('learning.dashboard') }}" class="block rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">My Learning</a>
                                <a href="{{ route('account.edit') }}" class="block rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Account settings</a>
                                @if ($me->is_admin)
                                    @php $toReview = \App\Models\Contribution::where('status', \App\Models\Contribution::PENDING)->count(); @endphp
                                    <a href="{{ route('admin.contributions.index') }}" class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        Admin · Contributions
                                        @if ($toReview > 0)
                                            <span class="flex-shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold whitespace-nowrap text-amber-800 tabular-nums">{{ $toReview }} to review</span>
                                        @endif
                                    </a>
                                    <a href="{{ route('admin.users.index') }}" class="block rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">Admin · Users</a>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('logout') }}" class="border-t border-gray-100 pt-1.5">
                                @csrf
                                <button type="submit" class="block w-full rounded-lg px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">Log out</button>
                            </form>
                        </div>
                    </details>
                @endguest
            </nav>
        </div>
    </header>

    @if (session('status'))
        <div class="mx-auto max-w-5xl px-4 pt-4" data-flash>
            <div role="status" class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                <svg class="mt-0.5 h-4 w-4 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.86-9.81a.75.75 0 0 0-1.22-.88l-3.48 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.06l2.5 2.5a.75.75 0 0 0 1.14-.09l4-5.5Z" clip-rule="evenodd"/></svg>
                <p class="flex-1">{{ session('status') }}</p>
                <button type="button" data-flash-close class="-m-1 rounded p-1 text-emerald-600 hover:bg-emerald-100" aria-label="Dismiss message">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z"/></svg>
                </button>
            </div>
        </div>
    @endif

    <main>
        {{ $slot }}
    </main>

    <footer class="mt-16 border-t border-gray-200 py-8 text-center text-sm text-gray-400">
        Free, for developers in Bangladesh and beyond. &middot; learn.bazardev.com
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.10.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.10.0/languages/bash.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.10.0/languages/dockerfile.min.js"></script>
    <script>
        hljs.highlightAll();

        // Account menu: close on outside click or Escape.
        document.addEventListener('click', (e) => {
            document.querySelectorAll('details[data-dropdown][open]').forEach((d) => {
                if (!d.contains(e.target)) d.removeAttribute('open');
            });
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('details[data-dropdown][open]').forEach((d) => d.removeAttribute('open'));
            }
        });

        // Dismiss the green status message.
        document.querySelectorAll('[data-flash-close]').forEach((btn) => {
            btn.addEventListener('click', () => btn.closest('[data-flash]').remove());
        });

        // Show / hide password.
        document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const input = document.getElementById(btn.dataset.togglePassword);
                const show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.textContent = show ? 'Hide' : 'Show';
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            });
        });
    </script>
</body>
</html>
