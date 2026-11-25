<x-layout title="DevOps Shikhbo — Zero to Hero">
    @php
        $core = array_filter($courses, fn ($course) => ($course['section'] ?? 'core') === 'core');
        $advanced = array_filter($courses, fn ($course) => ($course['section'] ?? 'core') === 'advanced');
        $ielts = array_filter($courses, fn ($course) => ($course['section'] ?? 'core') === 'ielts');
    @endphp

    <section class="mx-auto max-w-5xl px-4 pt-14 pb-10 text-center">
        <h1 class="text-4xl font-extrabold tracking-tight sm:text-5xl">Learn DevOps, <span class="text-blue-600">from zero</span></h1>
        <p class="mx-auto mt-4 max-w-2xl text-lg text-gray-500">In English and Bangla, with real projects — read it on GitHub or right here.</p>
        <p class="mx-auto mt-3 max-w-2xl text-sm text-gray-400">Take the courses in order, 1 to {{ count($core) }}: each one assumes the ones before it.</p>
    </section>

    {{-- Logged-in readers go straight back to where they stopped. --}}
    @if ($resume)
        <section class="mx-auto max-w-5xl px-4 pb-6">
            <x-resume-card :chapter="$resume" :label="$resume['is_next'] ? 'Up next' : 'Continue where you left off'" />
        </section>
    @endif

    <section class="mx-auto max-w-5xl px-4 pb-10">
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($core as $slug => $course)
                @include('courses.partials.home-card', ['slug' => $slug, 'course' => $course, 'number' => $loop->iteration, 'mine' => $progress[$slug] ?? null, 'coursePricing' => $pricing[$slug] ?? null, 'owned' => $hasSubscription || in_array($slug, $ownedCourses, true)])
            @endforeach
        </div>
    </section>

    @if (count($advanced) > 0)
        <section class="mx-auto max-w-5xl px-4 pb-20">
            <h2 class="text-lg font-semibold text-gray-700">Advanced</h2>
            <p class="mt-1 text-sm text-gray-400">Optional, after the core path — deeper topics for running things in production.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ($advanced as $slug => $course)
                    @include('courses.partials.home-card', ['slug' => $slug, 'course' => $course, 'number' => 'A'.$loop->iteration, 'mine' => $progress[$slug] ?? null, 'coursePricing' => $pricing[$slug] ?? null, 'owned' => $hasSubscription || in_array($slug, $ownedCourses, true)])
                @endforeach
            </div>
        </section>
    @endif

    @if (count($ielts) > 0)
        <section class="mx-auto max-w-5xl px-4 pb-20">
            <h2 class="text-lg font-semibold text-gray-700">IELTS Prep</h2>
            <p class="mt-1 text-sm text-gray-400">A separate track — Grammar, Reading, Writing, and Listening, each its own course.</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ($ielts as $slug => $course)
                    @include('courses.partials.home-card', ['slug' => $slug, 'course' => $course, 'number' => 'I'.$loop->iteration, 'mine' => $progress[$slug] ?? null, 'coursePricing' => $pricing[$slug] ?? null, 'owned' => $hasSubscription || in_array($slug, $ownedCourses, true)])
                @endforeach
            </div>
        </section>
    @endif
</x-layout>
