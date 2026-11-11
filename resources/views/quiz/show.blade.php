@php
    $course = $chapterInfo['course'];
    $slug = $chapterInfo['chapter'];
    $langQuery = $lang === 'bn' ? '?lang=bn' : '';
    $chapterUrl = route('course.chapter', [$course, $slug]).$langQuery;
    $quizUrl = route('quiz.show', [$course, $slug]).$langQuery;
    $total = count($questions);
    $need = $quiz->passMark($total);
@endphp

<x-layout :title="'Quiz · '.$chapterInfo['title']">
    <section class="mx-auto max-w-3xl px-4 pt-10 pb-20">
        <a href="{{ $chapterUrl }}" class="text-sm font-medium text-gray-500 hover:text-blue-600">&larr; Back to the chapter</a>

        <div class="mt-4 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-blue-600">{{ $chapterInfo['course_title'] }} <span class="text-gray-300">·</span> <span class="text-gray-500">Chapter {{ $chapterInfo['number'] }} quiz</span></p>
                <h1 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $chapterInfo['title'] }}</h1>
            </div>

            @if ($hasBn && ! $result)
                <div class="inline-flex rounded-lg border border-gray-200 bg-white p-0.5 text-sm">
                    <a href="{{ route('quiz.show', [$course, $slug]) }}" class="rounded-md px-3 py-1 {{ $lang === 'en' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:text-gray-900' }}">EN</a>
                    <a href="{{ route('quiz.show', [$course, $slug]) }}?lang=bn" class="rounded-md px-3 py-1 {{ $lang === 'bn' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:text-gray-900' }}">বাংলা</a>
                </div>
            @endif
        </div>

        {{-- ---------- Result ---------- --}}
        @if ($result)
            <div id="result" class="mt-6 rounded-2xl border p-5 sm:p-6 {{ $result['passed'] ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50' }}" role="status">
                <p class="text-lg font-bold tracking-tight {{ $result['passed'] ? 'text-emerald-900' : 'text-amber-900' }}">
                    {{ $result['passed'] ? 'You passed 🎉' : 'Not quite yet' }}
                    <span class="font-semibold tabular-nums">— {{ $result['score'] }} of {{ $result['total'] }} correct</span>
                </p>
                <p class="mt-1 text-sm {{ $result['passed'] ? 'text-emerald-800' : 'text-amber-800' }}">
                    @if ($result['passed'])
                        Well done. Read the explanations below if any answer surprised you.
                    @else
                        You need {{ $result['need'] }} correct to pass. Read the explanations below, look at the chapter again, then try once more.
                    @endif
                </p>

                @auth
                    @if ($saved)
                        <p class="mt-3 text-xs {{ $result['passed'] ? 'text-emerald-700' : 'text-amber-700' }}">Your result is saved in <a href="{{ route('learning.dashboard') }}" class="font-semibold underline underline-offset-2">My Learning</a>.</p>
                    @endif
                @else
                    <p class="mt-3 text-sm text-gray-700">
                        This result is not saved.
                        <a href="{{ route('login', ['next' => '/'.$course.'/'.$slug]) }}" class="font-semibold text-blue-700 underline underline-offset-2">Log in</a> or
                        <a href="{{ route('register', ['next' => '/'.$course.'/'.$slug]) }}" class="font-semibold text-blue-700 underline underline-offset-2">sign up free</a> to keep your score.
                    </p>
                @endauth

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <a href="{{ $quizUrl }}" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50 focus:outline-none focus-visible:ring-4 focus-visible:ring-gray-100">{{ $result['passed'] ? 'Take it again' : 'Try again' }}</a>
                    @if ($result['passed'] && $next)
                        <a href="{{ route('course.chapter', [$course, $next['chapter']]) }}{{ $langQuery }}" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-blue-700 focus:outline-none focus-visible:ring-4 focus-visible:ring-blue-200">Next chapter <x-icon name="arrow-right" class="h-4 w-4" /></a>
                    @else
                        <a href="{{ $chapterUrl }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-semibold text-blue-700 transition-colors hover:bg-blue-50">Back to the chapter</a>
                    @endif
                </div>
            </div>
        @else
            <p class="mt-3 text-sm text-gray-500">
                {{ $total }} questions · {{ $need }} right to pass · take it as often as you like.
                @if ($best)
                    <span class="ml-1 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium {{ $best->passed ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' }}">
                        @if ($best->passed)<x-icon name="check" class="h-3 w-3" />@endif
                        Your best: {{ $best->score }}/{{ $best->total }}
                    </span>
                @endif
            </p>

            @guest
                <p class="mt-1 text-xs text-gray-500">No account needed to try it. <a href="{{ route('login', ['next' => '/'.$course.'/'.$slug]) }}" class="font-medium text-blue-600 hover:text-blue-700">Log in</a> if you want your score saved.</p>
            @endguest
        @endif

        @if ($errors->any())
            <div class="mt-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                Please answer every question before checking — {{ $errors->count() === 1 ? 'one is' : $errors->count().' are' }} still empty.
            </div>
        @endif

        {{-- ---------- Questions ---------- --}}
        @if ($result)
            <ol class="mt-6 space-y-4">
                @foreach ($questions as $i => $question)
                    @php $row = $result['rows'][$i]; @endphp
                    <li class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-sm font-semibold text-gray-900"><span class="text-gray-400 tabular-nums">{{ $i + 1 }}.</span> {{ $quiz->format($quiz->text($question['q'], $lang)) }}</p>
                            <span class="flex-shrink-0 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $row['correct'] ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">{{ $row['correct'] ? 'Correct' : 'Not quite' }}</span>
                        </div>

                        <ul class="mt-4 space-y-2">
                            @foreach ($question['options'] as $j => $option)
                                @php
                                    $isAnswer = $j === $row['answer'];
                                    $isPick = $j === $row['chosen'];
                                    $tone = $isAnswer ? 'border-emerald-300 bg-emerald-50 text-emerald-900' : ($isPick ? 'border-rose-300 bg-rose-50 text-rose-900' : 'border-gray-100 text-gray-500');
                                @endphp
                                <li class="flex items-start gap-3 rounded-xl border p-3.5 text-sm {{ $tone }}">
                                    <span class="mt-0.5 flex h-4 w-4 flex-shrink-0 items-center justify-center">
                                        @if ($isAnswer)
                                            <x-icon name="check" class="h-4 w-4 text-emerald-600" />
                                        @elseif ($isPick)
                                            <x-icon name="x" class="h-4 w-4 text-rose-600" />
                                        @endif
                                    </span>
                                    <span class="min-w-0">
                                        {{ $quiz->format($quiz->text($option, $lang)) }}
                                        @if ($isAnswer)<span class="sr-only"> — correct answer</span>@endif
                                        @if ($isPick)<span class="sr-only"> — your answer</span>@endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>

                        <p class="mt-4 rounded-lg bg-gray-50 px-3.5 py-2.5 text-sm leading-relaxed text-gray-600"><span class="font-semibold text-gray-700">Why: </span>{{ $quiz->format($quiz->text($question['why'], $lang)) }}</p>
                    </li>
                @endforeach
            </ol>
        @else
            <form method="POST" action="{{ route('quiz.submit', [$course, $slug]) }}" id="quiz" class="mt-6">
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
                    <p class="text-xs text-gray-500">The correct answers and short explanations appear after you check.</p>
                    <x-button class="px-5 py-3">Check my answers</x-button>
                </div>
            </form>
        @endif
    </section>
</x-layout>
