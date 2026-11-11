@php
    // What an account gives you. 'live' => false shows a "Coming soon" tag, so the page never promises
    // something that is not built yet — flip to true as each feature ships.
    $features = [
        ['title' => 'Track your progress', 'text' => 'Mark chapters as done and see how far you are in every course.', 'live' => true],
        ['title' => 'Bookmark chapters', 'text' => 'Save the chapters you want to come back to.', 'live' => true],
        ['title' => 'Keep private notes', 'text' => 'Write your own notes beside each chapter — only you can see them.', 'live' => true],
        ['title' => 'Suggest resources', 'text' => 'Share a helpful link or tip; approved ones appear under the chapter with your name.', 'live' => true],
    ];
@endphp

<x-layout title="Create your free account">
    <section class="mx-auto grid max-w-5xl gap-10 px-4 pt-12 pb-20 lg:grid-cols-2 lg:gap-16">
        {{-- Why sign up --}}
        <div class="lg:pt-6">
            <p class="text-sm font-semibold text-blue-600">Free forever</p>
            <h1 class="mt-2 text-3xl font-extrabold tracking-tight sm:text-4xl">Make this course <span class="text-blue-600">yours</span></h1>
            <p class="mt-3 text-gray-500">Every chapter stays open to everyone, no account needed. An account just remembers things for you.</p>

            <ul class="mt-8 space-y-5">
                @foreach ($features as $feature)
                    <li class="flex gap-3">
                        <span class="mt-0.5 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                        </span>
                        <div>
                            <p class="flex flex-wrap items-center gap-2 font-semibold text-gray-900">
                                {{ $feature['title'] }}
                                @unless ($feature['live'])
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">Coming soon</span>
                                @endunless
                            </p>
                            <p class="mt-0.5 text-sm text-gray-500">{{ $feature['text'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- The form --}}
        <div>
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm sm:p-8">
                <h2 class="text-xl font-bold tracking-tight">Create your account</h2>
                <p class="mt-1 text-sm text-gray-500">Takes less than a minute.</p>

                <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-5" novalidate>
                    @csrf

                    {{-- Honeypot: hidden from people, tempting for bots. --}}
                    <div class="hidden" aria-hidden="true">
                        <label for="nickname_confirm">Leave this empty</label>
                        <input type="text" id="nickname_confirm" name="nickname_confirm" tabindex="-1" autocomplete="off">
                    </div>

                    <x-form.input name="name" label="Your name" autocomplete="name" required autofocus
                                  hint="Shown next to anything you contribute." />

                    <x-form.input name="email" label="Email" type="email" autocomplete="email" required placeholder="you@example.com"
                                  hint="Kept private. Never shown publicly unless you choose to." />

                    <x-form.input name="password" label="Password" type="password" autocomplete="new-password" required
                                  hint="At least 8 characters." />

                    <x-form.input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />

                    <x-button class="w-full">Create account</x-button>
                </form>
            </div>

            <p class="mt-6 text-center text-sm text-gray-500">
                Already have an account? <a href="{{ route('login') }}" class="font-semibold text-blue-600 hover:text-blue-700">Log in</a>
            </p>
        </div>
    </section>
</x-layout>
