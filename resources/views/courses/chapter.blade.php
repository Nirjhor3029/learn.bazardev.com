<x-layout :title="$title">
    <div class="mx-auto flex max-w-6xl gap-10 px-4 pt-10 pb-20">
        <aside class="hidden w-56 flex-shrink-0 lg:block">
            <div class="sticky top-6">
                <a href="{{ route('course.show', $course['slug']) }}" class="text-sm font-semibold text-gray-700 hover:text-blue-600">&larr; {{ $course['title'] }}</a>
                <ol class="mt-4 space-y-1 text-sm">
                    @foreach ($course['chapters'] as $chSlug => $ch)
                        @php $isCurrent = $chSlug === $slug; $available = $ch['status'] === 'available'; @endphp
                        <li>
                            <a href="{{ $available ? route('course.chapter', [$course['slug'], $chSlug]) : '#' }}"
                               class="block rounded-md px-2 py-1.5
                                      {{ $isCurrent ? 'bg-blue-50 font-semibold text-blue-700' : ($available ? 'text-gray-500 hover:bg-gray-50' : 'text-gray-300') }}">
                                {{ $loop->iteration }}. {{ $ch['title'] }}
                            </a>
                        </li>
                    @endforeach
                </ol>
            </div>
        </aside>

        <article class="min-w-0 flex-1">
            <p class="text-sm font-medium text-blue-600">{{ $course['title'] }}</p>
            <div class="markdown-body mt-2">
                {!! $html !!}
            </div>

            <div class="mt-10 flex items-center justify-between border-t border-gray-100 pt-6 text-sm">
                @if ($prev)
                    <a href="{{ route('course.chapter', [$course['slug'], $prev['slug']]) }}" class="text-gray-500 hover:text-blue-600">&larr; {{ $prev['title'] }}</a>
                @else
                    <span></span>
                @endif

                @if ($next && $next['status'] === 'available')
                    <a href="{{ route('course.chapter', [$course['slug'], $next['slug']]) }}" class="font-medium text-blue-600 hover:text-blue-700">{{ $next['title'] }} &rarr;</a>
                @elseif ($next)
                    <span class="text-gray-300">{{ $next['title'] }} (soon)</span>
                @endif
            </div>

            <a href="{{ $github_url }}" target="_blank" rel="noopener" class="mt-6 inline-block text-xs text-gray-400 hover:text-gray-600">GitHub e ei chapter edit/dekho →</a>
        </article>
    </div>
</x-layout>
