@php
    $langQuery = $lang === 'bn' ? '?lang=bn' : '';
    $courseUrl = route('course.show', $course);
    $examUrl = route('exam.show', $course).$langQuery;
@endphp

<x-layout :title="'Final exam · '.$courseTitle">
    <section class="mx-auto max-w-3xl px-4 pt-10 pb-20">
        <a href="{{ $courseUrl }}" class="text-sm font-medium text-gray-500 hover:text-blue-600">&larr; {{ $courseTitle }}</a>

        <div class="mt-4 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-blue-600">{{ $courseTitle }}</p>
                <h1 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">Final exam</h1>
            </div>

            @if ($questions && $hasBn)
                <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5 text-sm">
                    <a href="{{ route('exam.show', $course) }}" class="rounded-md px-3 py-1 {{ $lang === 'en' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:text-gray-900' }}">EN</a>
                    <a href="{{ route('exam.show', $course) }}?lang=bn" class="rounded-md px-3 py-1 {{ $lang === 'bn' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:text-gray-900' }}">বাংলা</a>
                </div>
            @endif
        </div>

        @if (session('exam_expired'))
            <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="alert">
                That exam session ran out, so nothing was graded. Here is a fresh set — please start again.
            </div>
        @endif

        {{-- ---------- Result ---------- --}}
        @if ($result)
            <div id="result" class="mt-6 rounded-2xl border p-5 sm:p-6 {{ $result['passed'] ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }}" role="status">
                <p class="text-lg font-bold tracking-tight {{ $result['passed'] ? 'text-emerald-900' : 'text-amber-900' }}">
                    {{ $result['passed'] ? 'You passed the final exam 🎉' : 'Not quite yet' }}
                    <span class="font-semibold tabular-nums">— {{ $result['score'] }} of {{ $result['total'] }} correct</span>
                </p>

                @if ($result['passed'])
                    <p class="mt-1 text-sm text-emerald-800">Congratulations — your certificate for {{ $courseTitle }} is ready.</p>
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        @if ($result['certificate'])
                            <a href="{{ route('certificate.show', $result['certificate']) }}" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-200">View my certificate <x-icon name="arrow-right" class="h-4 w-4" /></a>
                        @endif
                        <a href="{{ $courseUrl }}" class="inline-flex items-center rounded-lg px-3 py-2.5 text-sm font-semibold text-emerald-800 transition-colors hover:bg-emerald-100">Back to the course</a>
                    </div>
                    <p class="mt-3 text-xs text-emerald-700">Your certificate page can be opened by anyone who has its link, and it shows your name. Your email is never shown.</p>
                @else
                    <p class="mt-1 text-sm text-amber-800">You need {{ $result['need'] }} correct to pass. Right answers are not shown in the exam — read the chapters below again, then try once more with a new set of questions.</p>

                    @if ($result['review'])
                        <p class="mt-4 text-sm font-semibold text-amber-900">Chapters to read again</p>
                        <ul class="mt-2 space-y-1.5">
                            @foreach ($result['review'] as $chapter)
                                <li>
                                    <a href="{{ route('course.chapter', [$chapter['course'], $chapter['chapter']]) }}" class="inline-flex items-center gap-2 text-sm font-medium text-amber-900 underline decoration-amber-300 underline-offset-4 hover:text-amber-950">
                                        <span class="tabular-nums text-amber-700">{{ $chapter['number'] }}.</span> {{ $chapter['title'] }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <a href="{{ $examUrl }}" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-200">Try again with new questions</a>
                        <a href="{{ $courseUrl }}" class="inline-flex items-center rounded-lg px-3 py-2.5 text-sm font-semibold text-amber-900 transition-colors hover:bg-amber-100">Back to the course</a>
                    </div>
                @endif
            </div>

        {{-- ---------- Locked ---------- --}}
        @elseif ($remaining)
            <div class="mt-6 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
                <p class="flex items-center gap-2 font-semibold text-gray-900"><x-icon name="lock" class="h-4 w-4 text-gray-400" /> The exam is not open yet</p>
                <p class="mt-1 text-sm text-gray-500">
                    Pass the quiz of every chapter first. {{ count($remaining) === 1 ? 'One quiz is' : count($remaining).' quizzes are' }} still left:
                </p>
                <ul class="mt-4 divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-100">
                    @foreach ($remaining as $chapter)
                        <li>
                            <a href="{{ route('quiz.show', [$chapter['course'], $chapter['chapter']]) }}" class="flex items-center justify-between gap-3 px-4 py-3 text-sm transition-colors hover:bg-gray-50">
                                <span class="min-w-0"><span class="text-gray-400 tabular-nums">{{ $chapter['number'] }}.</span> <span class="font-medium text-gray-800">{{ $chapter['title'] }}</span></span>
                                <span class="flex flex-shrink-0 items-center gap-1 font-medium text-blue-600">Take quiz <x-icon name="arrow-right" class="h-4 w-4" /></span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

        {{-- ---------- Questions ---------- --}}
        @else
            <p class="mt-3 text-sm text-gray-500">
                {{ $total }} questions · {{ $need }} right to pass · no time limit.
                @if ($best)
                    <span class="ml-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium {{ $best->passed ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                        @if ($best->passed)<x-icon name="check" class="h-3 w-3" />@endif
                        Your best: {{ $best->score }}/{{ $best->total }}
                    </span>
                @endif
            </p>
            <p class="mt-1 text-xs text-gray-500">Every sitting is a new random set. The right answers are not shown afterwards — you will see which chapters to read again.</p>

            @if ($certificate)
                <p class="mt-3 rounded-lg bg-emerald-50 px-3.5 py-2.5 text-sm text-emerald-800">You already hold a certificate for this course. <a href="{{ route('certificate.show', $certificate->code) }}" class="font-semibold underline underline-offset-2">View it</a>. Taking the exam again does not change it.</p>
            @endif

            @if ($errors->any())
                <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                    Please answer every question — {{ $errors->count() === 1 ? 'one is' : $errors->count().' are' }} still empty.
                </div>
            @endif

            <form method="POST" action="{{ route('exam.submit', $course) }}" id="exam" class="mt-6">
                @csrf
                <input type="hidden" name="lang" value="{{ $lang }}">

                <ol class="space-y-4">
                    @foreach ($questions as $i => $question)
                        <li class="rounded-2xl border bg-white p-5 shadow-sm sm:p-6 {{ $errors->has("answers.$i") ? 'border-rose-300' : 'border-gray-100' }}">
                            <div role="group" aria-labelledby="q{{ $i }}">
                                <p id="q{{ $i }}" class="text-sm font-semibold text-gray-900"><span class="text-gray-400 tabular-nums">{{ $i + 1 }}.</span> {{ $quiz->format($quiz->text($question['q'], $lang)) }}</p>

                                <div class="mt-4 space-y-2">
                                    @foreach ($question['options'] as $j => $option)
                                        <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-200 bg-white p-3.5 text-sm text-gray-800 transition hover:border-gray-300 has-checked:border-blue-500 has-checked:bg-blue-50 has-focus-visible:ring-4 has-focus-visible:ring-blue-100">
                                            <input type="radio" name="answers[{{ $i }}]" value="{{ $j }}" required
                                                   class="mt-0.5 h-4 w-4 flex-shrink-0 accent-blue-600 focus:outline-none"
                                                   @checked((string) old("answers.$i") === (string) $j)>
                                            <span class="min-w-0">{{ $quiz->format($quiz->text($option, $lang)) }}</span>
                                        </label>
                                    @endforeach
                                </div>

                                @error("answers.$i")
                                    <p class="mt-3 text-sm text-rose-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </li>
                    @endforeach
                </ol>

                <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-gray-500">Check your answers before you send — each sitting counts as one attempt.</p>
                    <x-button class="px-5 py-3">Submit the exam</x-button>
                </div>
            </form>
        @endif
    </section>
</x-layout>
