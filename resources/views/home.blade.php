<x-layout title="Shikhbo — Zero to Hero">
    @php
        $categories = config('course.categories', []);
        $grouped = [];
        foreach ($categories as $key => $meta) {
            $grouped[$key] = array_filter($courses, fn ($course) => ($course['section'] ?? 'core') === $key);
        }
    @endphp

    <section class="mx-auto max-w-5xl px-4 pt-14 pb-10 text-center">
        <h1 class="text-4xl font-extrabold tracking-tight sm:text-5xl">Learn new skills, <span class="text-blue-600">from zero</span></h1>
        <p class="mx-auto mt-4 max-w-2xl text-lg text-gray-500">In English and Bangla, with real projects — read it on GitHub or right here.</p>
    </section>

    {{-- Logged-in readers go straight back to where they stopped. --}}
    @if ($resume)
        <section class="mx-auto max-w-5xl px-4 pb-6">
            <x-resume-card :chapter="$resume" :label="$resume['is_next'] ? 'Up next' : 'Continue where you left off'" />
        </section>
    @endif

    @foreach ($grouped as $key => $group)
        @continue(count($group) === 0)
        @php $meta = $categories[$key]; @endphp

        <section class="mx-auto max-w-5xl px-4 pb-10 {{ $loop->last ? 'pb-20' : '' }}">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-700">{{ $meta['label'] }}</h2>
                    <p class="mt-1 text-sm text-gray-400">{{ $meta['tagline'] }}</p>
                </div>

                @if ($key === 'core')
                    <a href="{{ config('course.github_repo') }}" target="_blank" rel="noopener"
                       class="hidden shrink-0 rounded-lg px-3 py-2 text-sm text-gray-500 transition-colors hover:text-gray-900 sm:block">View on GitHub</a>
                @endif
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ($group as $slug => $course)
                    @include('courses.partials.home-card', ['slug' => $slug, 'course' => $course, 'number' => $meta['prefix'].$loop->iteration, 'mine' => $progress[$slug] ?? null, 'coursePricing' => $pricing[$slug] ?? null, 'owned' => $hasSubscription || in_array($slug, $ownedCourses, true)])
                @endforeach
            </div>
        </section>
    @endforeach
</x-layout>
