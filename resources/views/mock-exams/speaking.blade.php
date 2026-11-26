<x-layout title="Mock test — Speaking">
    <section class="mx-auto max-w-3xl px-4 pt-10 pb-20">
        <div class="flex items-center justify-between">
            <p class="text-xs font-semibold tracking-wide text-blue-600 uppercase">Step 4 of 4 &middot; Speaking</p>
            <p id="timer" class="rounded-full bg-gray-900 px-3 py-1 text-sm font-semibold tabular-nums text-white">00:00</p>
        </div>
        <h1 class="mt-2 text-3xl font-extrabold tracking-tight">Speaking</h1>
        <p class="mt-3 text-gray-500">Self-run, like the mock test in the IELTS Speaking course — read each part aloud to yourself (or a study partner), ideally recording yourself. This isn't scored; after you finish, you'll see a Band 6 and a Band 8 sample answer to compare against.</p>

        <div class="mt-8 space-y-6">
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="font-semibold text-gray-900">Part 1 &middot; Introduction and interview</h2>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-gray-700">
                    @foreach ($tasks['part1'] as $q)
                        <li>{{ $q }}</li>
                    @endforeach
                </ul>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="font-semibold text-gray-900">Part 2 &middot; Cue card (1 min prep, up to 2 min talk)</h2>
                <p class="mt-3 font-medium text-gray-900">{{ $tasks['part2']['title'] }}</p>
                <p class="mt-1 text-sm text-gray-500">You should say:</p>
                <ul class="mt-1 list-disc space-y-1 pl-5 text-sm text-gray-700">
                    @foreach ($tasks['part2']['points'] as $point)
                        <li>{{ $point }}</li>
                    @endforeach
                </ul>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="font-semibold text-gray-900">Part 3 &middot; Discussion</h2>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-gray-700">
                    @foreach ($tasks['part3'] as $q)
                        <li>{{ $q }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        <form method="POST" action="{{ route('mock-tests.speaking.submit', [$attempt->mock_exam, $attempt]) }}" class="mt-8 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            @csrf
            <label for="notes" class="block text-sm font-medium text-gray-700">Optional: jot down a few notes on how it went, for your own reference</label>
            <textarea id="notes" name="notes" rows="6" placeholder="e.g. struggled to extend Part 1 answers, cue card went fine, forgot a word in Part 3…"
                      class="mt-2 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-blue-400 focus:ring-1 focus:ring-blue-400"></textarea>
            <button type="submit" class="mt-4 w-full rounded-lg bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-700">Finish mock test and see results</button>
        </form>
    </section>

    <script>
        (function () {
            var seconds = 0;
            var el = document.getElementById('timer');

            setInterval(function () {
                seconds += 1;
                var m = Math.floor(seconds / 60);
                var s = seconds % 60;
                el.textContent = (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
            }, 1000);
        })();
    </script>
</x-layout>
