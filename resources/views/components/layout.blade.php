<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'DevOps Shikhbo' }} — learn.bazardev.com</title>
    <meta name="description" content="Zero theke DevOps shikhun — Bangla te, practical project diye, free resource shoho.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.10.0/styles/github-dark.min.css">
</head>
<body class="bg-gray-50 text-gray-900 antialiased">
    <header class="border-b border-gray-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-bold tracking-tight">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-md bg-gray-900 text-sm text-white">⌘</span>
                DevOps Shikhbo
            </a>
            <a href="https://github.com/Nirjhor3029/devops-for-developers" target="_blank" rel="noopener"
               class="text-sm text-gray-500 hover:text-gray-900">GitHub e porun →</a>
        </div>
    </header>

    <main>
        {{ $slot }}
    </main>

    <footer class="mt-16 border-t border-gray-200 py-8 text-center text-sm text-gray-400">
        Bangladesh er developer der jonno, free-e. &middot; learn.bazardev.com
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.10.0/highlight.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.10.0/languages/bash.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.10.0/languages/dockerfile.min.js"></script>
    <script>hljs.highlightAll();</script>
</body>
</html>
