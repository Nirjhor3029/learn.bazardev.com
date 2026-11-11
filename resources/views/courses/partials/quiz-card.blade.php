{{-- "Check what you learned" card at the end of a chapter. $quiz comes from App\Services\Quiz::summary(). --}}
<section class="mt-12 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6" aria-labelledby="quiz-heading">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="min-w-0">
            <h2 id="quiz-heading" class="flex flex-wrap items-center gap-2 font-semibold text-gray-900">
                Check what you learned
                @if ($quiz['passed'])
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700"><x-icon name="check" class="h-3 w-3" /> Passed</span>
                @endif
            </h2>
            <p class="mt-0.5 text-sm text-gray-500 tabular-nums">
                {{ $quiz['count'] }} quick questions · {{ $quiz['need'] }} right to pass · no account needed
                @if ($quiz['best'])
                    · Your best: {{ $quiz['best']->score }}/{{ $quiz['best']->total }}
                @endif
            </p>
        </div>

        <a href="{{ route('quiz.show', [$course['slug'], $slug]) }}{{ $lang === 'bn' && $quiz['has_bn'] ? '?lang=bn' : '' }}"
           class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold shadow-sm transition-colors focus:outline-none focus-visible:ring-4 {{ $quiz['passed'] ? 'border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 focus-visible:ring-gray-100' : 'bg-blue-600 text-white hover:bg-blue-700 focus-visible:ring-blue-200' }}">
            {{ $quiz['best'] ? 'Take it again' : 'Take the quiz' }} <x-icon name="arrow-right" class="h-4 w-4" />
        </a>
    </div>
</section>
