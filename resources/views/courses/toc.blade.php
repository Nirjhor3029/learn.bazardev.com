<x-layout :title="$course['title']">
    <section class="mx-auto max-w-3xl px-4 pt-12 pb-20">
        <a href="{{ route('home') }}" class="text-sm text-gray-400 hover:text-gray-700">&larr; All courses</a>
        <h1 class="mt-3 text-3xl font-extrabold tracking-tight">{{ $course['title'] }}</h1>
        <p class="mt-2 text-gray-500">{{ $course['tagline'] }}</p>

        {{-- Logged-in readers: how far they are, and one button to carry on. --}}
        @if ($progress && $progress['total'] > 0)
            <div class="mt-6 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="font-semibold text-gray-900 tabular-nums">
                            @if ($progress['done'] === $progress['total'])
                                Course completed 🎉
                            @else
                                {{ $progress['done'] }} of {{ $progress['total'] }} chapters done
                            @endif
                        </p>
                        <p class="mt-0.5 text-sm text-gray-500">
                            @if ($progress['done'] === 0)
                                Mark a chapter as done at the end of it to track your progress.
                            @elseif ($nextUp)
                                Next: {{ $nextUp['title'] }}
                            @else
                                You finished every chapter. Come back any time to revise.
                            @endif
                        </p>
                    </div>
                    @if ($nextUp)
                        <a href="{{ route('course.chapter', [$course['slug'], $nextUp['chapter']]) }}"
                           class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700">
                            {{ $progress['done'] === 0 ? 'Start' : 'Continue' }} <x-icon name="arrow-right" class="h-4 w-4" />
                        </a>
                    @endif
                </div>
                <x-progress :done="$progress['done']" :total="$progress['total']" class="mt-4" />
            </div>
        @endif

        <ol class="mt-6 divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-100 bg-white">
            @foreach ($course['chapters'] as $slug => $chapter)
                @php
                    $available = $chapter['status'] === 'available';
                    $isDone = in_array($slug, $done, true);
                    $isBookmarked = in_array($slug, $bookmarked, true);
                @endphp
                <li>
                    <a href="{{ $available ? route('course.chapter', [$course['slug'], $slug]) : '#' }}"
                       class="flex items-center justify-between gap-3 px-5 py-4 {{ $available ? 'hover:bg-gray-50' : 'cursor-not-allowed opacity-50' }}">
                        <span class="flex min-w-0 items-center gap-3">
                            @if ($isDone)
                                <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
                                    <x-icon name="check" class="h-4 w-4" /><span class="sr-only">Done</span>
                                </span>
                            @else
                                <span class="flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-500">{{ $loop->iteration }}</span>
                            @endif
                            <span class="font-medium {{ $isDone ? 'text-gray-500' : '' }}">{{ $chapter['title'] }}</span>
                        </span>
                        <span class="flex flex-shrink-0 items-center gap-2">
                            @if ($isBookmarked)
                                <x-icon name="bookmark-solid" class="h-4 w-4 text-blue-600" /><span class="sr-only">Bookmarked</span>
                            @endif
                            @unless ($available)
                                <span class="text-xs text-gray-400">Soon</span>
                            @endunless
                        </span>
                    </a>
                </li>
            @endforeach
        </ol>

        {{-- Final exam + certificate --}}
        @if ($exam)
            <div class="mt-6 rounded-2xl border p-5 shadow-sm {{ $exam['certificate'] ? 'border-emerald-200 bg-emerald-50' : 'border-gray-100 bg-white' }}">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="flex flex-wrap items-center gap-2 font-semibold text-gray-900">
                            Final exam &amp; certificate
                            @if ($exam['certificate'])
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800"><x-icon name="check" class="h-3 w-3" /> Certificate earned</span>
                            @endif
                        </p>
                        <p class="mt-0.5 text-sm {{ $exam['certificate'] ? 'text-emerald-800' : 'text-gray-500' }} tabular-nums">
                            @if ($exam['certificate'])
                                You passed the final exam. Your certificate is ready.
                            @elseif (! auth()->check())
                                Pass every chapter quiz, then a {{ $exam['questions'] }}-question final exam ({{ $exam['need'] }} right) to earn a certificate. Free.
                            @elseif ($exam['ready'])
                                All chapter quizzes passed. The final exam has {{ $exam['questions'] }} questions; {{ $exam['need'] }} right earns your certificate.
                            @else
                                Pass the quiz of {{ $exam['remaining'] === 1 ? 'the last chapter' : 'the remaining '.$exam['remaining'].' chapters' }} to open the final exam.
                            @endif
                            @if ($exam['best'] && ! $exam['certificate'])
                                Your best: {{ $exam['best']->score }}/{{ $exam['best']->total }}.
                            @endif
                        </p>
                    </div>

                    @if ($exam['certificate'])
                        <a href="{{ route('certificate.show', $exam['certificate']->code) }}" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-emerald-200">View certificate <x-icon name="arrow-right" class="h-4 w-4" /></a>
                    @elseif (! auth()->check())
                        <a href="{{ route('login', ['next' => '/'.$course['slug']]) }}" class="inline-flex items-center rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50">Log in to start</a>
                    @else
                        <a href="{{ route('exam.show', $course['slug']) }}"
                           class="inline-flex items-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold shadow-sm transition-colors focus:outline-none focus-visible:ring-4 {{ $exam['ready'] ? 'bg-blue-600 text-white hover:bg-blue-700 focus-visible:ring-blue-200' : 'border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 focus-visible:ring-gray-100' }}">
                            @unless ($exam['ready'])<x-icon name="lock" class="h-4 w-4" />@endunless
                            {{ $exam['ready'] ? 'Take the final exam' : 'See what is left' }}
                        </a>
                    @endif
                </div>
            </div>
        @endif
    </section>
</x-layout>
