<x-layout title="Study plan">
    <section class="mx-auto max-w-3xl px-4 pt-12 pb-24">

        {{-- Hero: the day count carries the page --}}
        <div class="flex flex-wrap items-end justify-between gap-6">
            <div>
                <span class="sr-only">Day {{ $currentDay }} of {{ $totalDays }}</span>
                <p class="flex items-baseline gap-2" aria-hidden="true">
                    <span class="text-6xl font-black tracking-tight text-stone-900 tabular-nums">{{ $currentDay }}</span>
                    <span class="text-lg text-stone-400">/ {{ $totalDays }} days</span>
                </p>
                <p class="mt-1 text-sm text-stone-500">Grammar and vocabulary first, then reading and listening, then writing and speaking, then the exam itself.</p>
            </div>

            <div class="flex items-center gap-4 text-right">
                <div>
                    <p class="text-2xl font-bold text-stone-900 tabular-nums">{{ $progress['total'] > 0 ? round($progress['done'] / $progress['total'] * 100) : 0 }}%</p>
                    <p class="text-xs text-stone-400">through the plan</p>
                </div>
                <div class="h-10 w-px bg-stone-200"></div>
                <div>
                    <p class="text-2xl font-bold text-stone-900 tabular-nums">{{ $streak }}</p>
                    <p class="text-xs text-stone-400">{{ \Illuminate\Support\Str::plural('day', $streak) }} in a row</p>
                </div>
            </div>
        </div>

        <div class="mt-4 h-1 w-full bg-stone-100">
            <div class="h-1 bg-blue-600" style="width: {{ $progress['total'] > 0 ? round($progress['done'] / $progress['total'] * 100) : 0 }}%"></div>
        </div>

        @if ($finished)
            <div class="mt-6 border-l-2 border-emerald-500 pl-4">
                <p class="text-sm text-stone-700">You've reached day 90. Keep retaking the Mock Test and reviewing anything shaky until your score holds steady, then book the real exam.</p>
            </div>
        @endif

        {{-- Month navigation --}}
        <div class="mt-10 flex gap-6 border-b border-stone-200">
            @foreach ([1 => 'Foundation', 2 => 'Technique', 3 => 'Mock exams'] as $month => $tagline)
                @php $active = $month === (int) ceil($selectedDay / 30); @endphp
                <button type="button" data-month-tab="{{ $month }}"
                        class="relative pb-3 text-left transition {{ $active ? 'text-stone-900' : 'text-stone-400 hover:text-stone-600' }}"
                        aria-selected="{{ $active ? 'true' : 'false' }}">
                    <span class="block text-sm font-semibold">Month {{ $month }}</span>
                    <span class="block text-xs">{{ $tagline }}</span>
                    <span data-month-underline class="absolute inset-x-0 -bottom-px h-0.5 {{ $active ? 'bg-stone-900' : 'bg-transparent' }}"></span>
                </button>
            @endforeach
        </div>

        {{-- Day grids --}}
        @foreach ([1, 2, 3] as $month)
            <div data-month-grid="{{ $month }}" class="mt-6" @if ($month !== (int) ceil($selectedDay / 30)) hidden @endif>
                <div class="grid grid-cols-6 gap-1.5 sm:grid-cols-10">
                    @foreach (array_slice($overview, ($month - 1) * 30, 30) as $row)
                        @php
                            $cell = match ($row['status']) {
                                'completed' => 'border-transparent bg-emerald-50 text-emerald-800',
                                'today' => 'border-blue-600 bg-white text-blue-700',
                                'missed' => 'border-dashed border-amber-400 bg-white text-amber-700',
                                default => 'border-stone-100 bg-white text-stone-400',
                            };
                        @endphp
                        <a href="{{ route('study-plan.show', ['day' => $row['day']]) }}" data-day="{{ $row['day'] }}" title="{{ $row['label'] }}"
                           class="group relative flex aspect-square flex-col items-start justify-between rounded-md border p-1.5 text-xs font-medium transition hover:border-blue-400 {{ $cell }} {{ $row['day'] === $selectedDay ? 'ring-1 ring-stone-900' : '' }}">
                            <span class="tabular-nums">{{ $row['day'] }}</span>
                            @if ($row['status'] === 'completed')
                                <svg class="h-3 w-3 self-end" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.4 7.4a1 1 0 0 1-1.4 0L3.3 9.5a1 1 0 1 1 1.4-1.4l3.6 3.6 6.7-6.7a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                            @endif
                        </a>
                    @endforeach
                </div>
                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-stone-400">
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-emerald-50"></span>Done</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm border border-blue-600"></span>Today</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm border border-dashed border-amber-400"></span>Not done yet</span>
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm border border-stone-200"></span>Not started</span>
                </div>
            </div>
        @endforeach

        {{-- Selected day --}}
        <div class="mt-8 border-t border-stone-200 pt-6">
            @include('study-plan.partials.day-detail')
        </div>

        {{-- Catch up --}}
        @if (count($catchUp) > 0)
            <div class="mt-10">
                <h2 class="text-sm font-semibold text-stone-900">Still open from earlier</h2>
                <div class="mt-3 divide-y divide-stone-100">
                    @foreach ($catchUp as $day)
                        <div class="py-3">
                            <p class="text-xs font-medium text-amber-700">Day {{ $day['day'] }}</p>
                            @foreach ($day['chapters'] as $chapter)
                                @include('study-plan.partials.chapter-row', ['chapter' => $chapter])
                            @endforeach
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
                    t.classList.toggle('text-stone-900', active);
                    t.classList.toggle('text-stone-400', !active);
                    t.querySelector('[data-month-underline]').classList.toggle('bg-stone-900', active);
                    t.querySelector('[data-month-underline]').classList.toggle('bg-transparent', !active);
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
                                el.classList.toggle('ring-1', el.dataset.day === day);
                                el.classList.toggle('ring-stone-900', el.dataset.day === day);
                            });
                            history.pushState({}, '', `?day=${day}`);
                        })
                        .catch(() => { window.location.href = link.href; });
                });
            });
        })();
    </script>
</x-layout>
