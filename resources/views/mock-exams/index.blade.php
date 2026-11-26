<x-layout title="Mock tests">
    <section class="mx-auto max-w-5xl px-4 pt-14 pb-10">
        <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">IELTS mock tests</h1>
        <p class="mt-3 max-w-2xl text-gray-500">A full sitting — Listening, Reading, Writing, and Speaking — the same order as the real exam. Reading is auto-scored here; for Listening you take the real audio test on British Council's free site and bring your score back; Writing and Speaking are for practice, compared against Band 6 and Band 8 sample answers.</p>
    </section>

    @if ($errors->any())
        <section class="mx-auto max-w-5xl px-4 pb-4">
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mx-auto max-w-5xl px-4 pb-6">
        <div class="grid gap-4 sm:grid-cols-2">
            @foreach ($sets as $slug => $meta)
                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-semibold text-gray-900">{{ $meta['title'] }}</h2>
                    <p class="mt-1 text-sm text-gray-500">Listening &middot; Reading &middot; Writing &middot; Speaking</p>
                    <form method="POST" action="{{ route('mock-tests.start', $slug) }}" class="mt-4">
                        @csrf
                        <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Start mock test</button>
                    </form>
                </div>
            @endforeach
        </div>
    </section>

    @if ($attempts->isNotEmpty())
        <section class="mx-auto max-w-5xl px-4 pb-20">
            <h2 class="text-sm font-semibold tracking-wide text-gray-400 uppercase">Your attempts</h2>
            <div class="mt-3 space-y-2">
                @foreach ($attempts as $attempt)
                    <a href="{{ $attempt->status === 'completed' ? route('mock-tests.results', [$attempt->mock_exam, $attempt]) : route('mock-tests.listening', [$attempt->mock_exam, $attempt]) }}"
                       class="flex items-center justify-between rounded-xl border border-gray-100 bg-white px-4 py-3 text-sm shadow-sm hover:border-blue-200">
                        <span class="font-medium text-gray-900">{{ $attempt->title() }}</span>
                        <span class="text-xs font-semibold {{ $attempt->status === 'completed' ? 'text-emerald-600' : 'text-amber-600' }}">
                            {{ $attempt->status === 'completed' ? 'Completed' : 'In progress — continue' }}
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</x-layout>
