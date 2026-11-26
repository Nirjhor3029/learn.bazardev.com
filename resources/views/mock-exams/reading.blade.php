<x-layout title="Mock test — Reading">
    <section class="mx-auto max-w-3xl px-4 pt-10 pb-20">
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold tracking-wide text-blue-600 uppercase">Step 2 of 4 &middot; Reading</p>
            <p id="timer" class="rounded-full bg-gray-900 px-3 py-1 text-sm font-semibold tabular-nums text-white">60:00</p>
        </div>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight">Reading</h1>
        <p class="mt-3 text-gray-500">3 passages, {{ count($questions) }} questions, 60 minutes. Original content written for this site — not from any copyrighted test paper. The timer auto-submits your answers when it reaches zero, just like the real test.</p>

        <form id="reading-form" method="POST" action="{{ route('mock-tests.reading.submit', [$attempt->mock_exam, $attempt]) }}" class="mt-8 space-y-10">
            @csrf
            @foreach ($passages as $passage)
                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">Passage {{ $passage['number'] }} &middot; {{ $passage['title'] }}</h2>
                    <div class="prose prose-sm mt-4 max-w-none text-gray-700">{!! $passage['html'] !!}</div>
                </div>

                <div class="space-y-4">
                    @foreach ($questions as $i => $question)
                        @continue($question['passage'] !== $passage['number'])
                        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                            <p class="font-medium text-gray-900">{{ $i + 1 }}. {{ $question['q']['en'] }}</p>
                            @if (! empty($question['q']['bn']))
                                <p class="mt-1 text-sm text-gray-400">{{ $question['q']['bn'] }}</p>
                            @endif
                            <div class="mt-3 space-y-2">
                                @foreach ($question['options'] as $oi => $option)
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="radio" name="answers[{{ $i }}]" value="{{ $oi }}" required class="h-4 w-4">
                                        {{ $option['en'] }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endforeach

            <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700">Submit Reading and continue to Writing</button>
        </form>
    </section>

    <script>
        (function () {
            var seconds = 60 * 60;
            var el = document.getElementById('timer');
            var form = document.getElementById('reading-form');
            var submitted = false;

            var tick = setInterval(function () {
                seconds -= 1;
                var m = Math.floor(Math.max(seconds, 0) / 60);
                var s = Math.max(seconds, 0) % 60;
                el.textContent = (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);

                if (seconds <= 0 && !submitted) {
                    submitted = true;
                    clearInterval(tick);
                    form.submit();
                }
            }, 1000);
        })();
    </script>
</x-layout>
