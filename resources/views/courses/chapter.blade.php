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
            <div class="flex items-center justify-between">
                <p class="text-sm font-medium text-blue-600">{{ $course['title'] }}</p>

                @if ($has_bn)
                    <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5 text-sm">
                        <a href="{{ route('course.chapter', [$course['slug'], $slug]) }}"
                           class="rounded-md px-3 py-1 {{ $lang === 'en' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:text-gray-900' }}">EN</a>
                        <a href="{{ route('course.chapter', [$course['slug'], $slug]) }}?lang=bn"
                           class="rounded-md px-3 py-1 {{ $lang === 'bn' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:text-gray-900' }}">বাংলা</a>
                    </div>
                @endif
            </div>

            <div class="markdown-body mt-2" lang="{{ $lang }}">
                {!! $html !!}
            </div>

            <div class="mt-10 flex items-center justify-between border-t border-gray-100 pt-6 text-sm">
                @if ($prev)
                    <a href="{{ route('course.chapter', [$course['slug'], $prev['slug']]) }}{{ $lang === 'bn' ? '?lang=bn' : '' }}" class="text-gray-500 hover:text-blue-600">&larr; {{ $prev['title'] }}</a>
                @else
                    <span></span>
                @endif

                @if ($next && $next['status'] === 'available')
                    <a href="{{ route('course.chapter', [$course['slug'], $next['slug']]) }}{{ $lang === 'bn' ? '?lang=bn' : '' }}" class="font-medium text-blue-600 hover:text-blue-700">{{ $next['title'] }} &rarr;</a>
                @elseif ($next)
                    <span class="text-gray-300">{{ $next['title'] }} (soon)</span>
                @endif
            </div>

            <a href="{{ $github_url }}" target="_blank" rel="noopener" class="mt-6 inline-block text-xs text-gray-400 hover:text-gray-600">View this chapter on GitHub →</a>
        </article>
    </div>
</x-layout>
