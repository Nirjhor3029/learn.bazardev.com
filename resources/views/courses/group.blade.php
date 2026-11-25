<x-layout :title="$meta['label']">
    @php
        $categories = config('course.categories', []);
        $grouped = [];
        foreach ($meta['sections'] as $sectionKey) {
            $grouped[$sectionKey] = array_filter($courses, fn ($course) => ($course['section'] ?? 'core') === $sectionKey);
        }
        $showSectionHeadings = count($meta['sections']) > 1;
    @endphp

    <section class="mx-auto max-w-5xl px-4 pt-14 pb-10 text-center">
        <p class="text-sm font-semibold text-blue-600"><a href="{{ route('home') }}" class="hover:underline">&larr; All subjects</a></p>
        <h1 class="mt-3 text-4xl font-extrabold tracking-tight sm:text-5xl">{{ $meta['label'] }}</h1>
        <p class="mx-auto mt-4 max-w-2xl text-lg text-gray-500">{{ $meta['tagline'] }}</p>
    </section>

    @foreach ($grouped as $sectionKey => $group)
        @continue(count($group) === 0)
        @php $sectionMeta = $categories[$sectionKey]; @endphp

        <section class="mx-auto max-w-5xl px-4 pb-10 {{ $loop->last ? 'pb-20' : '' }}">
            @if ($showSectionHeadings)
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-700">{{ $sectionMeta['label'] }}</h2>
                        <p class="mt-1 text-sm text-gray-400">{{ $sectionMeta['tagline'] }}</p>
                    </div>

                    @if ($sectionKey === 'core')
                        <a href="{{ config('course.github_repo') }}" target="_blank" rel="noopener"
                           class="hidden shrink-0 rounded-lg px-3 py-2 text-sm text-gray-500 transition-colors hover:text-gray-900 sm:block">View on GitHub</a>
                    @endif
                </div>
            @endif

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @foreach ($group as $slug => $course)
                    @include('courses.partials.home-card', ['slug' => $slug, 'course' => $course, 'number' => $sectionMeta['prefix'].$loop->iteration, 'mine' => $progress[$slug] ?? null, 'coursePricing' => $pricing[$slug] ?? null, 'owned' => $hasSubscription || in_array($slug, $ownedCourses, true)])
                @endforeach
            </div>
        </section>
    @endforeach
</x-layout>
