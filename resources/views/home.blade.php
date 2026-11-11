<x-layout title="DevOps Shikhbo — Zero to Hero">
    <section class="mx-auto max-w-5xl px-4 pt-14 pb-10 text-center">
        <h1 class="text-4xl font-extrabold tracking-tight sm:text-5xl">Learn DevOps, <span class="text-blue-600">from zero</span></h1>
        <p class="mx-auto mt-4 max-w-2xl text-lg text-gray-500">In English and Bangla, with real projects — read it on GitHub or right here.</p>
    </section>

    {{-- Logged-in readers go straight back to where they stopped. --}}
    @if ($resume)
        <section class="mx-auto max-w-5xl px-4 pb-6">
            <x-resume-card :chapter="$resume" :label="$resume['is_next'] ? 'Up next' : 'Continue where you left off'" />
        </section>
    @endif

    <section class="mx-auto max-w-5xl px-4 pb-20">
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($courses as $slug => $course)
                @php
                    $available = $course['status'] === 'available';
                    $mine = $progress[$slug] ?? null;
                @endphp
                <a href="{{ $available ? route('course.show', $slug) : '#' }}"
                   class="group flex flex-col rounded-xl border border-gray-100 bg-white p-6 shadow-sm transition
                          {{ $available ? 'hover:border-blue-200 hover:shadow-md' : 'opacity-60 cursor-not-allowed' }}">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-semibold">{{ $course['title'] }}</h2>
                        @if ($mine && $mine['done'] === $mine['total'])
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700"><x-icon name="check" class="h-3 w-3" /> Completed</span>
                        @elseif ($available)
                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Ready</span>
                        @else
                            <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500">Soon</span>
                        @endif
                    </div>
                    <p class="mt-2 text-sm text-gray-500">{{ $course['tagline'] }}</p>

                    @if ($mine)
                        <div class="mt-auto flex items-center gap-3 pt-5">
                            <x-progress :done="$mine['done']" :total="$mine['total']" class="flex-1" />
                            <span class="text-xs font-medium text-gray-500 tabular-nums">{{ $mine['done'] }}/{{ $mine['total'] }} done</span>
                        </div>
                    @endif
                </a>
            @endforeach
        </div>
    </section>
</x-layout>
