<x-layout title="Study plan">
    <section class="mx-auto max-w-4xl px-4 pt-10 pb-20">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-semibold tracking-wide text-blue-600 uppercase">My Learning Journey</p>
                <h1 class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">90 Days &middot; Day 01 &mdash; Day 90</h1>
                <p class="mt-1 text-sm text-gray-500">Your learning progress at a glance.</p>
            </div>
            <div class="flex gap-3">
                <div class="rounded-xl border border-gray-100 bg-white px-4 py-3 text-center shadow-sm">
                    <p class="text-xl font-extrabold text-gray-900 tabular-nums">{{ $progress['total'] > 0 ? round($progress['done'] / $progress['total'] * 100) : 0 }}%</p>
                    <p class="text-xs text-gray-400">progress</p>
                </div>
                <div class="rounded-xl border border-gray-100 bg-white px-4 py-3 text-center shadow-sm">
                    <p class="text-xl font-extrabold text-gray-900 tabular-nums">{{ $streak }}🔥</p>
                    <p class="text-xs text-gray-400">day streak</p>
                </div>
            </div>
        </div>
        <x-progress :done="$progress['done']" :total="$progress['total']" class="mt-4" />
        <p class="mt-1 text-xs text-gray-400 tabular-nums">{{ $progress['done'] }}/{{ $progress['total'] }} chapters done &middot; Day {{ $currentDay }} of {{ $totalDays }}</p>

        @if ($finished)
            <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-sm text-emerald-800">
                You've reached the end of the 90-day plan 🎉 Keep repeating the Mock Test and reviewing weak chapters until you're consistently hitting your target band, then book the real exam.
            </div>
        @endif

        {{-- Month tabs --}}
        <div class="mt-8 grid grid-cols-3 gap-3" role="tablist" aria-label="Month">
            @foreach ([1 => 'Basic foundation', 2 => 'IELTS techniques', 3 => 'Advanced + mock exams'] as $month => $tagline)
                <button type="button" data-month-tab="{{ $month }}"
                        class="rounded-xl border p-4 text-left transition {{ $month === (int) ceil($selectedDay / 30) ? 'border-blue-300 bg-blue-50' : 'border-gray-100 bg-white hover:border-blue-200' }}"
                        aria-selected="{{ $month === (int) ceil($selectedDay / 30) ? 'true' : 'false' }}">
                    <p class="font-semibold text-gray-900">Month {{ $month }}</p>
                    <p class="text-xs text-gray-400">30 Days &middot; {{ $tagline }}</p>
                </button>
            @endforeach
        </div>

        {{-- Day grids, one per month --}}
        @foreach ([1, 2, 3] as $month)
            <div data-month-grid="{{ $month }}" class="mt-5" @if ($month !== (int) ceil($selectedDay / 30)) hidden @endif>
                <h2 class="text-sm font-semibold tracking-wide text-gray-400 uppercase">Month {{ $month }} &middot; Daily progress</h2>
                <div class="mt-3 grid grid-cols-6 gap-2 sm:grid-cols-10">
                    @foreach (array_slice($overview, ($month - 1) * 30, 30) as $row)
                        @php
                            $color = match ($row['status']) {
                                'completed' => 'bg-emerald-500 text-white',
                                'today' => 'bg-blue-600 text-white ring-2 ring-blue-300',
                                'missed' => 'bg-amber-400 text-white',
                                default => 'bg-gray-100 text-gray-500',
                            };
                        @endphp
                        <a href="{{ route('study-plan.show', ['day' => $row['day']]) }}" data-day="{{ $row['day'] }}" title="{{ $row['label'] }}"
                           class="flex aspect-square items-center justify-center rounded-lg text-sm font-semibold tabular-nums transition hover:opacity-80 {{ $color }} {{ $row['day'] === $selectedDay ? 'outline outline-2 outline-offset-1 outline-gray-900' : '' }}">
                            {{ $row['day'] }}
                        </a>
                    @endforeach
                </div>
                <div class="mt-3 flex flex-wrap gap-4 text-xs text-gray-500">
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Completed</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-blue-600"></span> Today</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span> Missed</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-gray-200"></span> Upcoming</span>
                </div>
            </div>
        @endforeach

        {{-- Selected day detail --}}
        <div class="mt-6 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
            @include('study-plan.partials.day-detail')
        </div>

        {{-- Catch up --}}
        @if (count($catchUp) > 0)
            <div class="mt-6">
                <h2 class="text-sm font-semibold tracking-wide text-amber-600 uppercase">Catch up — not finished yet</h2>
                <div class="mt-3 space-y-3">
                    @foreach ($catchUp as $day)
                        <div class="rounded-2xl border border-amber-100 bg-amber-50 p-5">
                            <p class="text-sm font-semibold text-amber-800">Day {{ $day['day'] }}</p>
                            <div class="mt-3 space-y-3">
                                @foreach ($day['chapters'] as $chapter)
                                    @include('study-plan.partials.chapter-row', ['chapter' => $chapter])
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    <script>
        (function () {
            const grids = document.querySelectorAll('[data-month-grid]');
            const tabs = document.querySelectorAll('[data-month-tab]');

            const showMonth = (month) => {
                grids.forEach((g) => { g.hidden = g.dataset.monthGrid !== String(month); });
                tabs.forEach((t) => {
                    const active = t.dataset.monthTab === String(month);
                    t.setAttribute('aria-selected', active ? 'true' : 'false');
                    t.classList.toggle('border-blue-300', active);
                    t.classList.toggle('bg-blue-50', active);
                    t.classList.toggle('border-gray-100', !active);
                    t.classList.toggle('bg-white', !active);
                });
            };

            tabs.forEach((tab) => tab.addEventListener('click', () => showMonth(tab.dataset.monthTab)));

            // Clicking a day fetches just the detail panel — falls back to the link's
            // normal navigation (a full page load with ?day=N) if fetch fails or JS is off.
            document.querySelectorAll('a[data-day]').forEach((link) => {
                link.addEventListener('click', (event) => {
                    event.preventDefault();
                    const day = link.dataset.day;

                    fetch(`/study-plan/day/${day}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                        .then((response) => { if (!response.ok) throw new Error(response.status); return response.text(); })
                        .then((html) => {
                            document.querySelector('[data-day-detail]').outerHTML = html;
                            document.querySelectorAll('a[data-day]').forEach((el) => {
                                el.classList.toggle('outline', el.dataset.day === day);
                                el.classList.toggle('outline-2', el.dataset.day === day);
                                el.classList.toggle('outline-offset-1', el.dataset.day === day);
                                el.classList.toggle('outline-gray-900', el.dataset.day === day);
                            });
                            history.pushState({}, '', `?day=${day}`);
                        })
                        .catch(() => { window.location.href = link.href; });
                });
            });
        })();
    </script>
</x-layout>
