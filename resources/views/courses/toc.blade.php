<x-layout :title="$course['title']">
    <section class="mx-auto max-w-3xl px-4 pt-12 pb-20">
        <a href="{{ route('home') }}" class="text-sm text-gray-400 hover:text-gray-700">&larr; All courses</a>
        <h1 class="mt-3 text-3xl font-extrabold tracking-tight">{{ $course['title'] }}</h1>
        <p class="mt-2 text-gray-500">{{ $course['tagline'] }}</p>

        <ol class="mt-8 divide-y divide-gray-100 rounded-xl border border-gray-100 bg-white">
            @foreach ($course['chapters'] as $slug => $chapter)
                @php $available = $chapter['status'] === 'available'; @endphp
                <li>
                    <a href="{{ $available ? route('course.chapter', [$course['slug'], $slug]) : '#' }}"
                       class="flex items-center justify-between px-5 py-4 {{ $available ? 'hover:bg-gray-50' : 'cursor-not-allowed opacity-50' }}">
                        <span class="flex items-center gap-3">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-500">{{ $loop->iteration }}</span>
                            <span class="font-medium">{{ $chapter['title'] }}</span>
                        </span>
                        @unless ($available)
                            <span class="text-xs text-gray-400">Soon</span>
                        @endunless
                    </a>
                </li>
            @endforeach
        </ol>
    </section>
</x-layout>
