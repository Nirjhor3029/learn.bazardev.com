<x-layout title="DevOps Shikhbo — Zero to Hero">
    <section class="mx-auto max-w-5xl px-4 pt-14 pb-10 text-center">
        <h1 class="text-4xl font-extrabold tracking-tight sm:text-5xl">Learn DevOps, <span class="text-blue-600">from zero</span></h1>
        <p class="mx-auto mt-4 max-w-2xl text-lg text-gray-500">In English and Bangla, with real projects — read it on GitHub or right here.</p>
    </section>

    <section class="mx-auto max-w-5xl px-4 pb-20">
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($courses as $slug => $course)
                @php $available = $course['status'] === 'available'; @endphp
                <a href="{{ $available ? route('course.show', $slug) : '#' }}"
                   class="group rounded-xl border border-gray-100 bg-white p-6 shadow-sm transition
                          {{ $available ? 'hover:border-blue-200 hover:shadow-md' : 'opacity-60 cursor-not-allowed' }}">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold">{{ $course['title'] }}</h2>
                        @if ($available)
                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Ready</span>
                        @else
                            <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500">Soon</span>
                        @endif
                    </div>
                    <p class="mt-2 text-sm text-gray-500">{{ $course['tagline'] }}</p>
                </a>
            @endforeach
        </div>
    </section>
</x-layout>
