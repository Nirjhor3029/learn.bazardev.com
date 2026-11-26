<x-layout title="Mock test — Writing">
    <section class="mx-auto max-w-3xl px-4 pt-10 pb-20">
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold tracking-wide text-blue-600 uppercase">Step 3 of 4 &middot; Writing</p>
            <p id="timer" class="rounded-full bg-gray-900 px-3 py-1 text-sm font-semibold tabular-nums text-white">60:00</p>
        </div>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight">Writing</h1>
        <p class="mt-3 text-gray-500">Task 1 and Task 2, 60 minutes total — just like the real test. This isn't auto-scored (no AI or human examiner here); after you submit, you'll see your own answer next to a Band 6 and a Band 8 sample for comparison.</p>

        <form id="writing-form" method="POST" action="{{ route('mock-tests.writing.submit', [$attempt->mock_exam, $attempt]) }}" class="mt-8 space-y-8">
            @csrf
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="font-semibold text-gray-900">{{ $tasks['task1']['title'] }}</h2>
                <div class="prose prose-sm mt-3 max-w-none text-gray-700">{!! $tasks['task1']['prompt_html'] !!}</div>
                <textarea name="task1" rows="10" placeholder="Write your Task 1 answer here…"
                          class="mt-4 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:ring-1 focus:ring-blue-400"></textarea>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="font-semibold text-gray-900">{{ $tasks['task2']['title'] }}</h2>
                <p class="mt-3 whitespace-pre-line text-sm text-gray-700">{{ $tasks['task2']['prompt'] }}</p>
                <textarea name="task2" rows="14" placeholder="Write your Task 2 answer here…"
                          class="mt-4 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:ring-1 focus:ring-blue-400"></textarea>
            </div>

            <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700">Submit Writing and continue to Speaking</button>
        </form>
    </section>

    <script>
        (function () {
            var seconds = 60 * 60;
            var el = document.getElementById('timer');
            var form = document.getElementById('writing-form');
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
