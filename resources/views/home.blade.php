<x-layout title="Shikhbo — Zero to Hero">
    <section class="mx-auto max-w-5xl px-4 pt-14 pb-10 text-center">
        <h1 class="text-4xl font-extrabold tracking-tight sm:text-5xl">Learn new skills, <span class="text-blue-600">from zero</span></h1>
        <p class="mx-auto mt-4 max-w-2xl text-lg text-gray-500">In English and Bangla, with real projects — read it on GitHub or right here.</p>
    </section>

    {{-- Logged-in readers go straight back to where they stopped, whichever subject it was. --}}
    @if ($resume)
        <section class="mx-auto max-w-5xl px-4 pb-6">
            <x-resume-card :chapter="$resume" :label="$resume['is_next'] ? 'Up next' : 'Continue where you left off'" />
        </section>
    @endif

    <section class="mx-auto max-w-5xl px-4 pb-20">
        <h2 class="text-lg font-semibold text-gray-700">Pick a subject</h2>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            @foreach ($groups as $key => $group)
                <a href="{{ route('course.group', $key) }}"
                   class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition hover:border-blue-200 hover:shadow-md">
                    <h3 class="text-xl font-bold text-gray-900">{{ $group['label'] }}</h3>
                    <p class="mt-2 text-sm text-gray-500">{{ $group['tagline'] }}</p>
                    <p class="mt-4 text-sm font-semibold text-blue-600">{{ $group['count'] }} {{ \Illuminate\Support\Str::plural('course', $group['count']) }} &rarr;</p>
                </a>
            @endforeach
        </div>
    </section>
</x-layout>
