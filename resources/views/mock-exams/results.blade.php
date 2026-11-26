<x-layout title="Mock test results">
    <section class="mx-auto max-w-3xl px-4 pt-14 pb-20">
        <h1 class="text-3xl font-extrabold tracking-tight">Mock test results</h1>
        <p class="mt-3 text-gray-500">{{ $attempt->title() }} &middot; completed {{ $attempt->completed_at?->diffForHumans() }}</p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold tracking-wide text-gray-400 uppercase">Listening</h2>
                <p class="mt-2 text-3xl font-extrabold text-gray-900">Band {{ \App\Models\MockExamAttempt::bandLabel($attempt->listening_band) }}</p>
                <p class="mt-1 text-sm text-gray-500">{{ $attempt->listening_self_reported_raw }}/{{ $attempt->listening_total }} correct (self-reported from British Council's test)</p>
            </div>
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold tracking-wide text-gray-400 uppercase">Reading</h2>
                <p class="mt-2 text-3xl font-extrabold text-gray-900">Band {{ \App\Models\MockExamAttempt::bandLabel($attempt->reading_band) }}</p>
                <p class="mt-1 text-sm text-gray-500">{{ $attempt->reading_correct }}/{{ $attempt->reading_total }} correct, auto-graded</p>
            </div>
        </div>
        <p class="mt-2 text-xs text-gray-400">Band scores are approximate — the official conversion varies slightly from test to test.</p>

        <div class="mt-10 space-y-8">
            <div>
                <h2 class="text-xl font-bold">Writing — practice only, not scored</h2>
                <p class="mt-1 text-sm text-gray-500">Compare your own answer with a Band 6 and a Band 8 sample.</p>

                <div class="mt-4 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900">{{ $tasks['task1']['title'] }}</h3>
                    <div class="mt-4 grid gap-4 sm:grid-cols-3">
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-gray-400 uppercase">Your answer</p>
                            <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $attempt->writing_task1_text ?: '(left blank)' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-amber-500 uppercase">Band 6 sample</p>
                            <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $tasks['task1']['sample_band6'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-emerald-600 uppercase">Band 8 sample</p>
                            <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $tasks['task1']['sample_band8'] }}</p>
                        </div>
                    </div>
                </div>

                <div class="mt-4 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h3 class="font-semibold text-gray-900">{{ $tasks['task2']['title'] }}</h3>
                    <div class="mt-4 grid gap-4 sm:grid-cols-3">
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-gray-400 uppercase">Your answer</p>
                            <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $attempt->writing_task2_text ?: '(left blank)' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-amber-500 uppercase">Band 6 sample</p>
                            <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $tasks['task2']['sample_band6'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-emerald-600 uppercase">Band 8 sample</p>
                            <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $tasks['task2']['sample_band8'] }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h2 class="text-xl font-bold">Speaking — practice only, not scored</h2>
                <p class="mt-1 text-sm text-gray-500">Compare the kind of Part 2 answer a Band 6 and a Band 8 speaker might give for "{{ $speakingTasks['part2']['title'] }}" against your own notes.</p>

                <div class="mt-4 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-gray-400 uppercase">Your notes</p>
                            <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $attempt->speaking_notes ?: '(no notes)' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-amber-500 uppercase">Band 6 sample</p>
                            <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $speakingTasks['sample_band6'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold tracking-wide text-emerald-600 uppercase">Band 8 sample</p>
                            <p class="mt-2 whitespace-pre-line text-sm text-gray-700">{{ $speakingTasks['sample_band8'] }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <a href="{{ route('mock-tests.index') }}" class="mt-10 inline-block text-sm font-medium text-blue-600 hover:underline">← Back to mock tests</a>
    </section>
</x-layout>
